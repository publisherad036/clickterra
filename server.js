require('dotenv').config();
const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const path = require('path');

// Import routes
const campaignRoutes = require('./src/routes/campaigns');
const creativeRoutes = require('./src/routes/creatives');
const adServerRoutes = require('./src/routes/adserver');

// Import database to initialize
require('./src/models/database');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Request logging middleware
app.use((req, res, next) => {
  console.log(`${new Date().toISOString()} - ${req.method} ${req.url}`);
  next();
});

// Static files for admin interface
app.use(express.static(path.join(__dirname, 'public')));

// API Routes
app.use('/api/campaigns', campaignRoutes);
app.use('/api/creatives', creativeRoutes);
app.use('/api/adserver', adServerRoutes);

// VAST serving endpoint (direct access)
app.get('/vast', (req, res, next) => {
  const adServerController = require('./src/controllers/adServerController');
  adServerController.serveVAST(req, res);
});

// Tracking endpoint (direct access)
app.get('/track/:event/:creative_id/:request_id', (req, res) => {
  const adServerController = require('./src/controllers/adServerController');
  adServerController.trackEvent(req, res);
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    timestamp: new Date().toISOString(),
    service: 'ClickTerra Video AdServer'
  });
});

// Root endpoint
app.get('/', (req, res) => {
  res.json({
    service: 'ClickTerra Video AdServer',
    version: '1.0.0',
    description: 'VAST Video Ad Server with RTB support',
    endpoints: {
      vast: '/vast?placement=<id>&campaign_id=<id>',
      campaigns: '/api/campaigns',
      creatives: '/api/creatives',
      tracking: '/track/:event/:creative_id/:request_id',
      stats: '/api/adserver/stats'
    }
  });
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(err.status || 500).json({
    error: err.message || 'Internal server error',
    timestamp: new Date().toISOString()
  });
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({
    error: 'Not found',
    path: req.url
  });
});

// Start server
app.listen(PORT, () => {
  console.log('='.repeat(50));
  console.log('ClickTerra Video AdServer');
  console.log('='.repeat(50));
  console.log(`Server running on port ${PORT}`);
  console.log(`Environment: ${process.env.NODE_ENV || 'development'}`);
  console.log(`VAST endpoint: http://localhost:${PORT}/vast`);
  console.log(`Admin API: http://localhost:${PORT}/api`);
  console.log('='.repeat(50));
});

module.exports = app;
