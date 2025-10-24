const express = require('express');
const router = express.Router();
const adServerController = require('../controllers/adServerController');

// Ad serving routes
router.get('/vast', adServerController.serveVAST.bind(adServerController));
router.get('/track/:event/:creative_id/:request_id', adServerController.trackEvent.bind(adServerController));

// Statistics routes
router.get('/stats', adServerController.getStats.bind(adServerController));
router.get('/requests', adServerController.getRecentRequests.bind(adServerController));
router.get('/events', adServerController.getRecentEvents.bind(adServerController));

module.exports = router;
