const { v4: uuidv4 } = require('uuid');
const db = require('../models/database');

class CampaignController {
  /**
   * Create a new campaign
   */
  async createCampaign(req, res) {
    try {
      const { name, advertiser, start_date, end_date, daily_budget, total_budget } = req.body;
      
      if (!name) {
        return res.status(400).json({ error: 'Campaign name is required' });
      }

      const campaignId = uuidv4();
      
      await db.run(
        `INSERT INTO campaigns (id, name, advertiser, start_date, end_date, daily_budget, total_budget)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [campaignId, name, advertiser, start_date, end_date, daily_budget, total_budget]
      );

      const campaign = await db.get('SELECT * FROM campaigns WHERE id = ?', [campaignId]);
      
      res.status(201).json({ 
        success: true, 
        campaign 
      });
    } catch (error) {
      console.error('Error creating campaign:', error);
      res.status(500).json({ error: 'Failed to create campaign' });
    }
  }

  /**
   * Get all campaigns
   */
  async getAllCampaigns(req, res) {
    try {
      const campaigns = await db.all('SELECT * FROM campaigns ORDER BY created_at DESC');
      res.json({ success: true, campaigns });
    } catch (error) {
      console.error('Error fetching campaigns:', error);
      res.status(500).json({ error: 'Failed to fetch campaigns' });
    }
  }

  /**
   * Get a specific campaign
   */
  async getCampaign(req, res) {
    try {
      const { id } = req.params;
      const campaign = await db.get('SELECT * FROM campaigns WHERE id = ?', [id]);
      
      if (!campaign) {
        return res.status(404).json({ error: 'Campaign not found' });
      }

      const creatives = await db.all('SELECT * FROM video_creatives WHERE campaign_id = ?', [id]);
      
      res.json({ 
        success: true, 
        campaign: {
          ...campaign,
          creatives
        }
      });
    } catch (error) {
      console.error('Error fetching campaign:', error);
      res.status(500).json({ error: 'Failed to fetch campaign' });
    }
  }

  /**
   * Update a campaign
   */
  async updateCampaign(req, res) {
    try {
      const { id } = req.params;
      const { name, advertiser, status, start_date, end_date, daily_budget, total_budget } = req.body;
      
      const campaign = await db.get('SELECT * FROM campaigns WHERE id = ?', [id]);
      
      if (!campaign) {
        return res.status(404).json({ error: 'Campaign not found' });
      }

      await db.run(
        `UPDATE campaigns 
         SET name = COALESCE(?, name),
             advertiser = COALESCE(?, advertiser),
             status = COALESCE(?, status),
             start_date = COALESCE(?, start_date),
             end_date = COALESCE(?, end_date),
             daily_budget = COALESCE(?, daily_budget),
             total_budget = COALESCE(?, total_budget)
         WHERE id = ?`,
        [name, advertiser, status, start_date, end_date, daily_budget, total_budget, id]
      );

      const updatedCampaign = await db.get('SELECT * FROM campaigns WHERE id = ?', [id]);
      
      res.json({ 
        success: true, 
        campaign: updatedCampaign 
      });
    } catch (error) {
      console.error('Error updating campaign:', error);
      res.status(500).json({ error: 'Failed to update campaign' });
    }
  }

  /**
   * Delete a campaign
   */
  async deleteCampaign(req, res) {
    try {
      const { id } = req.params;
      
      const result = await db.run('DELETE FROM campaigns WHERE id = ?', [id]);
      
      if (result.changes === 0) {
        return res.status(404).json({ error: 'Campaign not found' });
      }

      res.json({ 
        success: true, 
        message: 'Campaign deleted successfully' 
      });
    } catch (error) {
      console.error('Error deleting campaign:', error);
      res.status(500).json({ error: 'Failed to delete campaign' });
    }
  }

  /**
   * Get campaign statistics
   */
  async getCampaignStats(req, res) {
    try {
      const { id } = req.params;
      
      const campaign = await db.get('SELECT * FROM campaigns WHERE id = ?', [id]);
      
      if (!campaign) {
        return res.status(404).json({ error: 'Campaign not found' });
      }

      const impressions = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE campaign_id = ? AND event_type = 'impression'`,
        [id]
      );

      const clicks = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE campaign_id = ? AND event_type = 'click'`,
        [id]
      );

      const completes = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE campaign_id = ? AND event_type = 'complete'`,
        [id]
      );

      res.json({
        success: true,
        stats: {
          campaign_id: id,
          campaign_name: campaign.name,
          impressions: impressions.count || 0,
          clicks: clicks.count || 0,
          completes: completes.count || 0,
          ctr: impressions.count > 0 ? ((clicks.count / impressions.count) * 100).toFixed(2) : 0,
          completion_rate: impressions.count > 0 ? ((completes.count / impressions.count) * 100).toFixed(2) : 0
        }
      });
    } catch (error) {
      console.error('Error fetching campaign stats:', error);
      res.status(500).json({ error: 'Failed to fetch campaign stats' });
    }
  }
}

module.exports = new CampaignController();
