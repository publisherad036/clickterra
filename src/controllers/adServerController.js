const { v4: uuidv4 } = require('uuid');
const db = require('../models/database');
const VASTGenerator = require('../utils/vastGenerator');

class AdServerController {
  constructor() {
    this.vastGenerator = new VASTGenerator();
  }

  /**
   * Serve VAST ad tag
   * GET /vast?placement=<placement_id>&w=<width>&h=<height>
   */
  async serveVAST(req, res) {
    try {
      const { placement, w, h, campaign_id } = req.query;
      const requestId = uuidv4();
      
      // Log ad request
      const ipAddress = req.ip || req.connection.remoteAddress;
      const userAgent = req.get('user-agent');

      // Get active campaign
      let query = 'SELECT * FROM campaigns WHERE status = "active"';
      let params = [];
      
      if (campaign_id) {
        query += ' AND id = ?';
        params.push(campaign_id);
      }
      
      const campaigns = await db.all(query, params);
      
      if (campaigns.length === 0) {
        return res.status(204).send(); // No ads available
      }

      // Simple ad selection - get first active campaign
      const selectedCampaign = campaigns[0];
      
      // Get creatives for this campaign
      const creatives = await db.all(
        'SELECT * FROM video_creatives WHERE campaign_id = ?',
        [selectedCampaign.id]
      );

      if (creatives.length === 0) {
        return res.status(204).send(); // No creatives available
      }

      // Select a creative (simple random selection)
      const selectedCreative = creatives[Math.floor(Math.random() * creatives.length)];

      // Log ad request
      await db.run(
        `INSERT INTO ad_requests (campaign_id, creative_id, ip_address, user_agent, placement)
         VALUES (?, ?, ?, ?, ?)`,
        [selectedCampaign.id, selectedCreative.id, ipAddress, userAgent, placement]
      );

      // Generate VAST XML
      const vastXML = this.vastGenerator.generateVAST(selectedCreative, requestId);

      res.set('Content-Type', 'application/xml');
      res.send(vastXML);
    } catch (error) {
      console.error('Error serving VAST:', error);
      res.status(500).send('<?xml version="1.0" encoding="UTF-8"?><VAST version="3.0"></VAST>');
    }
  }

  /**
   * Track ad events
   * GET /track/:event/:creative_id/:request_id
   */
  async trackEvent(req, res) {
    try {
      const { event, creative_id, request_id } = req.params;
      const ipAddress = req.ip || req.connection.remoteAddress;
      const userAgent = req.get('user-agent');

      // Get creative and campaign info
      const creative = await db.get(
        'SELECT * FROM video_creatives WHERE id = ?',
        [creative_id]
      );

      if (creative) {
        await db.run(
          `INSERT INTO ad_impressions (creative_id, campaign_id, event_type, ip_address, user_agent)
           VALUES (?, ?, ?, ?, ?)`,
          [creative_id, creative.campaign_id, event, ipAddress, userAgent]
        );

        console.log(`Tracked event: ${event} for creative ${creative_id} (request ${request_id})`);
      }

      // Return 1x1 transparent GIF
      const gif = Buffer.from(
        'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
        'base64'
      );
      
      res.set('Content-Type', 'image/gif');
      res.set('Cache-Control', 'no-cache, no-store, must-revalidate');
      res.send(gif);
    } catch (error) {
      console.error('Error tracking event:', error);
      res.status(200).send();
    }
  }

  /**
   * Get overall statistics
   */
  async getStats(req, res) {
    try {
      const totalCampaigns = await db.get('SELECT COUNT(*) as count FROM campaigns');
      const activeCampaigns = await db.get('SELECT COUNT(*) as count FROM campaigns WHERE status = "active"');
      const totalCreatives = await db.get('SELECT COUNT(*) as count FROM video_creatives');
      const totalImpressions = await db.get('SELECT COUNT(*) as count FROM ad_impressions WHERE event_type = "impression"');
      const totalClicks = await db.get('SELECT COUNT(*) as count FROM ad_impressions WHERE event_type = "click"');
      const totalCompletes = await db.get('SELECT COUNT(*) as count FROM ad_impressions WHERE event_type = "complete"');
      const totalRequests = await db.get('SELECT COUNT(*) as count FROM ad_requests');

      res.json({
        success: true,
        stats: {
          campaigns: {
            total: totalCampaigns.count,
            active: activeCampaigns.count
          },
          creatives: totalCreatives.count,
          impressions: totalImpressions.count,
          clicks: totalClicks.count,
          completes: totalCompletes.count,
          requests: totalRequests.count,
          ctr: totalImpressions.count > 0 ? ((totalClicks.count / totalImpressions.count) * 100).toFixed(2) : 0,
          completion_rate: totalImpressions.count > 0 ? ((totalCompletes.count / totalImpressions.count) * 100).toFixed(2) : 0
        }
      });
    } catch (error) {
      console.error('Error fetching stats:', error);
      res.status(500).json({ error: 'Failed to fetch stats' });
    }
  }

  /**
   * Get recent ad requests
   */
  async getRecentRequests(req, res) {
    try {
      const limit = parseInt(req.query.limit) || 50;
      
      const requests = await db.all(
        `SELECT ar.*, c.name as campaign_name, vc.title as creative_title
         FROM ad_requests ar
         LEFT JOIN campaigns c ON ar.campaign_id = c.id
         LEFT JOIN video_creatives vc ON ar.creative_id = vc.id
         ORDER BY ar.timestamp DESC
         LIMIT ?`,
        [limit]
      );

      res.json({
        success: true,
        requests
      });
    } catch (error) {
      console.error('Error fetching recent requests:', error);
      res.status(500).json({ error: 'Failed to fetch recent requests' });
    }
  }

  /**
   * Get recent tracking events
   */
  async getRecentEvents(req, res) {
    try {
      const limit = parseInt(req.query.limit) || 50;
      
      const events = await db.all(
        `SELECT ai.*, c.name as campaign_name, vc.title as creative_title
         FROM ad_impressions ai
         LEFT JOIN campaigns c ON ai.campaign_id = c.id
         LEFT JOIN video_creatives vc ON ai.creative_id = vc.id
         ORDER BY ai.timestamp DESC
         LIMIT ?`,
        [limit]
      );

      res.json({
        success: true,
        events
      });
    } catch (error) {
      console.error('Error fetching recent events:', error);
      res.status(500).json({ error: 'Failed to fetch recent events' });
    }
  }
}

module.exports = new AdServerController();
