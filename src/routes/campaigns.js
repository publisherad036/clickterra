const express = require('express');
const router = express.Router();
const campaignController = require('../controllers/campaignController');

// Campaign routes
router.post('/', campaignController.createCampaign.bind(campaignController));
router.get('/', campaignController.getAllCampaigns.bind(campaignController));
router.get('/:id', campaignController.getCampaign.bind(campaignController));
router.put('/:id', campaignController.updateCampaign.bind(campaignController));
router.delete('/:id', campaignController.deleteCampaign.bind(campaignController));
router.get('/:id/stats', campaignController.getCampaignStats.bind(campaignController));

module.exports = router;
