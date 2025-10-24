const express = require('express');
const router = express.Router();
const creativeController = require('../controllers/creativeController');

// Creative routes
router.post('/', creativeController.createCreative.bind(creativeController));
router.get('/', creativeController.getAllCreatives.bind(creativeController));
router.get('/:id', creativeController.getCreative.bind(creativeController));
router.put('/:id', creativeController.updateCreative.bind(creativeController));
router.delete('/:id', creativeController.deleteCreative.bind(creativeController));
router.get('/:id/stats', creativeController.getCreativeStats.bind(creativeController));

module.exports = router;
