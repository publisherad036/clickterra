# Example API Usage

This file contains example cURL commands for testing the ClickTerra Video AdServer API.

## Campaign Management

### Create a Campaign

```bash
curl -X POST http://localhost:3000/api/campaigns \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Summer Video Campaign 2024",
    "advertiser": "Acme Corporation",
    "start_date": "2024-06-01",
    "end_date": "2024-08-31",
    "daily_budget": 1000,
    "total_budget": 90000
  }'
```

### Get All Campaigns

```bash
curl http://localhost:3000/api/campaigns
```

### Get Specific Campaign

```bash
curl http://localhost:3000/api/campaigns/{campaign_id}
```

### Update Campaign

```bash
curl -X PUT http://localhost:3000/api/campaigns/{campaign_id} \
  -H "Content-Type: application/json" \
  -d '{
    "status": "active",
    "daily_budget": 1500
  }'
```

### Delete Campaign

```bash
curl -X DELETE http://localhost:3000/api/campaigns/{campaign_id}
```

### Get Campaign Statistics

```bash
curl http://localhost:3000/api/campaigns/{campaign_id}/stats
```

## Video Creative Management

### Create a Video Creative

```bash
curl -X POST http://localhost:3000/api/creatives \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_id": "{campaign_id}",
    "title": "Product Launch Video Ad",
    "description": "30 second product showcase",
    "duration": 30,
    "video_url": "https://example.com/videos/product-ad.mp4",
    "video_type": "video/mp4",
    "bitrate": 1200,
    "width": 1920,
    "height": 1080,
    "click_through_url": "https://example.com/products/new-launch",
    "skip_offset": 5
  }'
```

### Get All Creatives

```bash
curl http://localhost:3000/api/creatives
```

### Get Creatives for Specific Campaign

```bash
curl "http://localhost:3000/api/creatives?campaign_id={campaign_id}"
```

### Get Specific Creative

```bash
curl http://localhost:3000/api/creatives/{creative_id}
```

### Update Creative

```bash
curl -X PUT http://localhost:3000/api/creatives/{creative_id} \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Updated Product Launch Video",
    "duration": 25,
    "skip_offset": 3
  }'
```

### Delete Creative

```bash
curl -X DELETE http://localhost:3000/api/creatives/{creative_id}
```

### Get Creative Statistics

```bash
curl http://localhost:3000/api/creatives/{creative_id}/stats
```

## Ad Serving

### Request VAST Tag

```bash
# Basic request
curl "http://localhost:3000/vast?placement=homepage"

# With specific campaign
curl "http://localhost:3000/vast?placement=homepage&campaign_id={campaign_id}"

# With dimensions
curl "http://localhost:3000/vast?placement=homepage&w=1920&h=1080"
```

### Test Tracking Pixel (will return 1x1 transparent GIF)

```bash
# Impression tracking
curl "http://localhost:3000/track/impression/{creative_id}/{request_id}"

# Video start
curl "http://localhost:3000/track/start/{creative_id}/{request_id}"

# First quartile (25%)
curl "http://localhost:3000/track/firstQuartile/{creative_id}/{request_id}"

# Midpoint (50%)
curl "http://localhost:3000/track/midpoint/{creative_id}/{request_id}"

# Third quartile (75%)
curl "http://localhost:3000/track/thirdQuartile/{creative_id}/{request_id}"

# Complete (100%)
curl "http://localhost:3000/track/complete/{creative_id}/{request_id}"

# Click tracking
curl "http://localhost:3000/track/click/{creative_id}/{request_id}"

# Skip
curl "http://localhost:3000/track/skip/{creative_id}/{request_id}"
```

## Statistics & Reporting

### Get Overall Statistics

```bash
curl http://localhost:3000/api/adserver/stats
```

### Get Recent Ad Requests

```bash
curl "http://localhost:3000/api/adserver/requests?limit=50"
```

### Get Recent Tracking Events

```bash
curl "http://localhost:3000/api/adserver/events?limit=100"
```

## Health Check

```bash
curl http://localhost:3000/health
```

## Complete Workflow Example

```bash
# 1. Create a campaign
CAMPAIGN_RESPONSE=$(curl -s -X POST http://localhost:3000/api/campaigns \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Campaign",
    "advertiser": "Test Advertiser",
    "status": "active"
  }')

CAMPAIGN_ID=$(echo $CAMPAIGN_RESPONSE | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
echo "Created campaign: $CAMPAIGN_ID"

# 2. Create a video creative
CREATIVE_RESPONSE=$(curl -s -X POST http://localhost:3000/api/creatives \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_id": "'$CAMPAIGN_ID'",
    "title": "Test Video Ad",
    "description": "Test creative",
    "duration": 30,
    "video_url": "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4",
    "click_through_url": "https://example.com",
    "width": 1280,
    "height": 720,
    "skip_offset": 5
  }')

CREATIVE_ID=$(echo $CREATIVE_RESPONSE | grep -o '"id":"[^"]*"' | cut -d'"' -f4)
echo "Created creative: $CREATIVE_ID"

# 3. Request VAST tag
echo "Requesting VAST tag..."
curl "http://localhost:3000/vast?placement=test&campaign_id=$CAMPAIGN_ID"

# 4. Simulate tracking events
echo "Simulating tracking events..."
REQUEST_ID="test-request-123"
curl "http://localhost:3000/track/impression/$CREATIVE_ID/$REQUEST_ID"
curl "http://localhost:3000/track/start/$CREATIVE_ID/$REQUEST_ID"
curl "http://localhost:3000/track/firstQuartile/$CREATIVE_ID/$REQUEST_ID"
curl "http://localhost:3000/track/midpoint/$CREATIVE_ID/$REQUEST_ID"
curl "http://localhost:3000/track/thirdQuartile/$CREATIVE_ID/$REQUEST_ID"
curl "http://localhost:3000/track/complete/$CREATIVE_ID/$REQUEST_ID"

# 5. Check statistics
echo "Campaign statistics:"
curl "http://localhost:3000/api/campaigns/$CAMPAIGN_ID/stats"

echo "\nCreative statistics:"
curl "http://localhost:3000/api/creatives/$CREATIVE_ID/stats"
```

## Testing with Real Video Players

### Video.js with IMA Plugin

```html
<!DOCTYPE html>
<html>
<head>
  <link href="https://vjs.zencdn.net/7.20.3/video-js.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/videojs-contrib-ads@6/dist/videojs.ads.css" rel="stylesheet">
</head>
<body>
  <video id="my-video" class="video-js" controls preload="auto" width="640" height="360">
    <source src="content.mp4" type="video/mp4">
  </video>

  <script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
  <script src="//imasdk.googleapis.com/js/sdkloader/ima3.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/videojs-contrib-ads@6/dist/videojs.ads.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/videojs-ima@2/dist/videojs.ima.min.js"></script>
  
  <script>
    var player = videojs('my-video');
    player.ima({
      adTagUrl: 'http://localhost:3000/vast?placement=homepage'
    });
  </script>
</body>
</html>
```

### JW Player

```html
<!DOCTYPE html>
<html>
<head>
  <script src="https://cdn.jwplayer.com/libraries/YOUR_KEY.js"></script>
</head>
<body>
  <div id="player"></div>
  
  <script>
    jwplayer("player").setup({
      file: "content.mp4",
      advertising: {
        client: "vast",
        schedule: {
          adbreak1: {
            offset: "pre",
            tag: "http://localhost:3000/vast?placement=homepage"
          }
        }
      }
    });
  </script>
</body>
</html>
```

## Notes

- Replace `{campaign_id}`, `{creative_id}`, and `{request_id}` with actual IDs
- All endpoints return JSON except VAST (returns XML) and tracking pixels (return GIF)
- Use proper error handling in production
- Tracking events are fired automatically by compliant VAST players
