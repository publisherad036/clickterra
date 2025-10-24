const sqlite3 = require('sqlite3').verbose();
const path = require('path');

class Database {
  constructor() {
    const dbPath = process.env.DB_PATH || path.join(__dirname, '../../data/adserver.db');
    this.db = new sqlite3.Database(dbPath, (err) => {
      if (err) {
        console.error('Error opening database:', err.message);
      } else {
        console.log('Connected to SQLite database');
        this.initialize();
      }
    });
  }

  initialize() {
    this.db.serialize(() => {
      // Campaigns table
      this.db.run(`
        CREATE TABLE IF NOT EXISTS campaigns (
          id TEXT PRIMARY KEY,
          name TEXT NOT NULL,
          advertiser TEXT,
          status TEXT DEFAULT 'active',
          start_date TEXT,
          end_date TEXT,
          daily_budget REAL,
          total_budget REAL,
          created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
      `);

      // Video Creatives table
      this.db.run(`
        CREATE TABLE IF NOT EXISTS video_creatives (
          id TEXT PRIMARY KEY,
          campaign_id TEXT NOT NULL,
          title TEXT NOT NULL,
          description TEXT,
          duration INTEGER NOT NULL,
          video_url TEXT NOT NULL,
          video_type TEXT DEFAULT 'video/mp4',
          bitrate INTEGER,
          width INTEGER,
          height INTEGER,
          click_through_url TEXT,
          skip_offset INTEGER DEFAULT 5,
          created_at TEXT DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE
        )
      `);

      // Tracking Events table
      this.db.run(`
        CREATE TABLE IF NOT EXISTS tracking_events (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          creative_id TEXT NOT NULL,
          event_type TEXT NOT NULL,
          event_url TEXT NOT NULL,
          FOREIGN KEY (creative_id) REFERENCES video_creatives(id) ON DELETE CASCADE
        )
      `);

      // Ad Impressions table
      this.db.run(`
        CREATE TABLE IF NOT EXISTS ad_impressions (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          creative_id TEXT NOT NULL,
          campaign_id TEXT NOT NULL,
          event_type TEXT NOT NULL,
          ip_address TEXT,
          user_agent TEXT,
          timestamp TEXT DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (creative_id) REFERENCES video_creatives(id),
          FOREIGN KEY (campaign_id) REFERENCES campaigns(id)
        )
      `);

      // Ad Requests table
      this.db.run(`
        CREATE TABLE IF NOT EXISTS ad_requests (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          campaign_id TEXT,
          creative_id TEXT,
          ip_address TEXT,
          user_agent TEXT,
          placement TEXT,
          timestamp TEXT DEFAULT CURRENT_TIMESTAMP
        )
      `);

      console.log('Database tables initialized');
    });
  }

  run(sql, params = []) {
    return new Promise((resolve, reject) => {
      this.db.run(sql, params, function(err) {
        if (err) reject(err);
        else resolve({ lastID: this.lastID, changes: this.changes });
      });
    });
  }

  get(sql, params = []) {
    return new Promise((resolve, reject) => {
      this.db.get(sql, params, (err, row) => {
        if (err) reject(err);
        else resolve(row);
      });
    });
  }

  all(sql, params = []) {
    return new Promise((resolve, reject) => {
      this.db.all(sql, params, (err, rows) => {
        if (err) reject(err);
        else resolve(rows);
      });
    });
  }

  close() {
    return new Promise((resolve, reject) => {
      this.db.close((err) => {
        if (err) reject(err);
        else resolve();
      });
    });
  }
}

module.exports = new Database();
