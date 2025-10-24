const { v4: uuidv4 } = require('uuid');
const db = require('../models/database');

class CreativeController {
  /**
   * Create a new video creative
   */
  async createCreative(req, res) {
    try {
      const { 
        campaign_id, title, description, duration, video_url, 
        video_type, bitrate, width, height, click_through_url, skip_offset 
      } = req.body;
      
      if (!campaign_id || !title || !duration || !video_url) {
        return res.status(400).json({ 
          error: 'campaign_id, title, duration, and video_url are required' 
        });
      }

      // Verify campaign exists
      const campaign = await db.get('SELECT * FROM campaigns WHERE id = ?', [campaign_id]);
      if (!campaign) {
        return res.status(404).json({ error: 'Campaign not found' });
      }

      const creativeId = uuidv4();
      
      await db.run(
        `INSERT INTO video_creatives 
         (id, campaign_id, title, description, duration, video_url, video_type, 
          bitrate, width, height, click_through_url, skip_offset)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          creativeId, campaign_id, title, description, duration, video_url, 
          video_type || 'video/mp4', bitrate || 800, width || 1280, height || 720, 
          click_through_url, skip_offset || 5
        ]
      );

      const creative = await db.get('SELECT * FROM video_creatives WHERE id = ?', [creativeId]);
      
      res.status(201).json({ 
        success: true, 
        creative 
      });
    } catch (error) {
      console.error('Error creating creative:', error);
      res.status(500).json({ error: 'Failed to create creative' });
    }
  }

  /**
   * Get all creatives
   */
  async getAllCreatives(req, res) {
    try {
      const { campaign_id } = req.query;
      
      let query = 'SELECT * FROM video_creatives';
      let params = [];
      
      if (campaign_id) {
        query += ' WHERE campaign_id = ?';
        params.push(campaign_id);
      }
      
      query += ' ORDER BY created_at DESC';
      
      const creatives = await db.all(query, params);
      res.json({ success: true, creatives });
    } catch (error) {
      console.error('Error fetching creatives:', error);
      res.status(500).json({ error: 'Failed to fetch creatives' });
    }
  }

  /**
   * Get a specific creative
   */
  async getCreative(req, res) {
    try {
      const { id } = req.params;
      const creative = await db.get('SELECT * FROM video_creatives WHERE id = ?', [id]);
      
      if (!creative) {
        return res.status(404).json({ error: 'Creative not found' });
      }

      res.json({ 
        success: true, 
        creative 
      });
    } catch (error) {
      console.error('Error fetching creative:', error);
      res.status(500).json({ error: 'Failed to fetch creative' });
    }
  }

  /**
   * Update a creative
   */
  async updateCreative(req, res) {
    try {
      const { id } = req.params;
      const { 
        title, description, duration, video_url, video_type, 
        bitrate, width, height, click_through_url, skip_offset 
      } = req.body;
      
      const creative = await db.get('SELECT * FROM video_creatives WHERE id = ?', [id]);
      
      if (!creative) {
        return res.status(404).json({ error: 'Creative not found' });
      }

      await db.run(
        `UPDATE video_creatives 
         SET title = COALESCE(?, title),
             description = COALESCE(?, description),
             duration = COALESCE(?, duration),
             video_url = COALESCE(?, video_url),
             video_type = COALESCE(?, video_type),
             bitrate = COALESCE(?, bitrate),
             width = COALESCE(?, width),
             height = COALESCE(?, height),
             click_through_url = COALESCE(?, click_through_url),
             skip_offset = COALESCE(?, skip_offset)
         WHERE id = ?`,
        [title, description, duration, video_url, video_type, bitrate, width, height, click_through_url, skip_offset, id]
      );

      const updatedCreative = await db.get('SELECT * FROM video_creatives WHERE id = ?', [id]);
      
      res.json({ 
        success: true, 
        creative: updatedCreative 
      });
    } catch (error) {
      console.error('Error updating creative:', error);
      res.status(500).json({ error: 'Failed to update creative' });
    }
  }

  /**
   * Delete a creative
   */
  async deleteCreative(req, res) {
    try {
      const { id } = req.params;
      
      const result = await db.run('DELETE FROM video_creatives WHERE id = ?', [id]);
      
      if (result.changes === 0) {
        return res.status(404).json({ error: 'Creative not found' });
      }

      res.json({ 
        success: true, 
        message: 'Creative deleted successfully' 
      });
    } catch (error) {
      console.error('Error deleting creative:', error);
      res.status(500).json({ error: 'Failed to delete creative' });
    }
  }

  /**
   * Get creative statistics
   */
  async getCreativeStats(req, res) {
    try {
      const { id } = req.params;
      
      const creative = await db.get('SELECT * FROM video_creatives WHERE id = ?', [id]);
      
      if (!creative) {
        return res.status(404).json({ error: 'Creative not found' });
      }

      const impressions = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE creative_id = ? AND event_type = 'impression'`,
        [id]
      );

      const starts = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE creative_id = ? AND event_type = 'start'`,
        [id]
      );

      const firstQuartile = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE creative_id = ? AND event_type = 'firstQuartile'`,
        [id]
      );

      const midpoint = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE creative_id = ? AND event_type = 'midpoint'`,
        [id]
      );

      const thirdQuartile = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE creative_id = ? AND event_type = 'thirdQuartile'`,
        [id]
      );

      const completes = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE creative_id = ? AND event_type = 'complete'`,
        [id]
      );

      const clicks = await db.get(
        `SELECT COUNT(*) as count FROM ad_impressions 
         WHERE creative_id = ? AND event_type = 'click'`,
        [id]
      );

      res.json({
        success: true,
        stats: {
          creative_id: id,
          creative_title: creative.title,
          impressions: impressions.count || 0,
          starts: starts.count || 0,
          firstQuartile: firstQuartile.count || 0,
          midpoint: midpoint.count || 0,
          thirdQuartile: thirdQuartile.count || 0,
          completes: completes.count || 0,
          clicks: clicks.count || 0,
          completion_rate: starts.count > 0 ? ((completes.count / starts.count) * 100).toFixed(2) : 0,
          ctr: impressions.count > 0 ? ((clicks.count / impressions.count) * 100).toFixed(2) : 0
        }
      });
    } catch (error) {
      console.error('Error fetching creative stats:', error);
      res.status(500).json({ error: 'Failed to fetch creative stats' });
    }
  }
}

module.exports = new CreativeController();
