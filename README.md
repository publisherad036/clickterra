# ClickTerra Video AdServer

🎬 AdServer RTB DSP + SSP Platform dengan dukungan VAST Video Ads

## Fitur Utama

### 1. VAST Video Ad Serving
- **VAST 3.0 XML Generation**: Generate VAST XML secara otomatis untuk video ads
- **Instream Video Ads**: Dukungan penuh untuk pre-roll, mid-roll, dan post-roll ads
- **Skip Controls**: Konfigurasi skip offset untuk setiap creative
- **Multiple Formats**: Dukungan berbagai format video (MP4, WebM, dll)

### 2. Campaign Management
- Create, read, update, delete campaigns
- Budget management (daily & total budget)
- Campaign scheduling (start date & end date)
- Campaign status management (active/inactive)

### 3. Video Creative Management
- Upload dan manage video creatives
- Konfigurasi video properties (duration, bitrate, resolution)
- Click-through URL management
- Multiple creatives per campaign

### 4. Ad Tracking & Analytics
- Impression tracking
- Video event tracking:
  - Start
  - First Quartile (25%)
  - Midpoint (50%)
  - Third Quartile (75%)
  - Complete (100%)
  - Click
  - Skip
  - Pause/Resume
  - Mute/Unmute
  - Fullscreen
- Real-time statistics
- CTR (Click-Through Rate) calculation
- Completion rate calculation

### 5. Admin Dashboard
- Web-based admin interface
- Real-time statistics dashboard
- Campaign & creative management UI
- VAST tag generator

## Installation

### Prerequisites
- Node.js 14.x atau lebih tinggi
- npm atau yarn

### Setup

1. Clone repository:
```bash
git clone https://github.com/publisherad036/clickterra.git
cd clickterra
```

2. Install dependencies:
```bash
npm install
```

3. Setup environment variables:
```bash
cp .env.example .env
```

Edit `.env` file sesuai kebutuhan:
```env
PORT=3000
NODE_ENV=development
DB_PATH=./data/adserver.db
SERVER_URL=http://localhost:3000
```

4. Start server:
```bash
npm start
```

Untuk development dengan auto-reload:
```bash
npm run dev
```

## Penggunaan

### Admin Dashboard

Akses admin dashboard di browser:
```
http://localhost:3000
```

Dashboard menyediakan:
- Statistics overview
- Campaign management
- Creative management
- VAST tag generator

### API Endpoints

#### Campaign Management

**Create Campaign**
```bash
POST /api/campaigns
Content-Type: application/json

{
  "name": "Summer Campaign 2024",
  "advertiser": "Brand X",
  "start_date": "2024-06-01",
  "end_date": "2024-08-31",
  "daily_budget": 1000,
  "total_budget": 90000
}
```

**Get All Campaigns**
```bash
GET /api/campaigns
```

**Get Campaign Details**
```bash
GET /api/campaigns/:id
```

**Update Campaign**
```bash
PUT /api/campaigns/:id
Content-Type: application/json

{
  "status": "active",
  "daily_budget": 1500
}
```

**Delete Campaign**
```bash
DELETE /api/campaigns/:id
```

**Get Campaign Statistics**
```bash
GET /api/campaigns/:id/stats
```

#### Creative Management

**Create Video Creative**
```bash
POST /api/creatives
Content-Type: application/json

{
  "campaign_id": "campaign-uuid",
  "title": "Product Launch Video",
  "description": "New product announcement",
  "duration": 30,
  "video_url": "https://example.com/videos/ad.mp4",
  "click_through_url": "https://example.com/landing-page",
  "width": 1280,
  "height": 720,
  "skip_offset": 5
}
```

**Get All Creatives**
```bash
GET /api/creatives
# Optional: filter by campaign
GET /api/creatives?campaign_id=campaign-uuid
```

**Get Creative Details**
```bash
GET /api/creatives/:id
```

**Update Creative**
```bash
PUT /api/creatives/:id
Content-Type: application/json

{
  "title": "Updated Title",
  "duration": 25
}
```

**Delete Creative**
```bash
DELETE /api/creatives/:id
```

**Get Creative Statistics**
```bash
GET /api/creatives/:id/stats
```

#### Ad Serving

**Request VAST Tag**
```bash
GET /vast?placement=homepage&campaign_id=campaign-uuid

# Response: VAST 3.0 XML
<?xml version="1.0" encoding="UTF-8"?>
<VAST version="3.0">
  ...
</VAST>
```

**Ad Tracking**
```bash
# Automatically called by video player
GET /track/:event/:creative_id/:request_id

# Events: impression, start, firstQuartile, midpoint, 
#         thirdQuartile, complete, click, skip, etc.
```

#### Statistics

**Get Overall Statistics**
```bash
GET /api/adserver/stats
```

**Get Recent Ad Requests**
```bash
GET /api/adserver/requests?limit=50
```

**Get Recent Tracking Events**
```bash
GET /api/adserver/events?limit=50
```

### Integration dengan Video Player

#### Video.js Example

```html
<!DOCTYPE html>
<html>
<head>
  <link href="https://vjs.zencdn.net/7.20.3/video-js.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/videojs-contrib-ads@6/dist/videojs.ads.css" rel="stylesheet">
</head>
<body>
  <video id="my-video" class="video-js" controls preload="auto" width="640" height="360">
    <source src="your-content-video.mp4" type="video/mp4">
  </video>

  <script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
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

#### JW Player Example

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
      file: "your-content-video.mp4",
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

## Database Schema

### campaigns
- `id`: Campaign UUID (primary key)
- `name`: Campaign name
- `advertiser`: Advertiser name
- `status`: active/inactive
- `start_date`: Campaign start date
- `end_date`: Campaign end date
- `daily_budget`: Daily budget limit
- `total_budget`: Total budget limit
- `created_at`: Creation timestamp

### video_creatives
- `id`: Creative UUID (primary key)
- `campaign_id`: Foreign key to campaigns
- `title`: Creative title
- `description`: Creative description
- `duration`: Video duration in seconds
- `video_url`: Video file URL
- `video_type`: MIME type (video/mp4, etc.)
- `bitrate`: Video bitrate
- `width`: Video width
- `height`: Video height
- `click_through_url`: Landing page URL
- `skip_offset`: Skip button delay in seconds
- `created_at`: Creation timestamp

### ad_impressions
- `id`: Auto-increment ID
- `creative_id`: Foreign key to video_creatives
- `campaign_id`: Foreign key to campaigns
- `event_type`: Event name (impression, start, complete, etc.)
- `ip_address`: User IP address
- `user_agent`: User agent string
- `timestamp`: Event timestamp

### ad_requests
- `id`: Auto-increment ID
- `campaign_id`: Selected campaign
- `creative_id`: Selected creative
- `ip_address`: User IP address
- `user_agent`: User agent string
- `placement`: Placement identifier
- `timestamp`: Request timestamp

## Architecture

```
┌─────────────────────────────────────────────────┐
│              Video Player / Website              │
└─────────────────┬───────────────────────────────┘
                  │
                  │ VAST Request
                  │
┌─────────────────▼───────────────────────────────┐
│           ClickTerra AdServer API                │
│                                                   │
│  ┌─────────────────────────────────────────┐   │
│  │  Campaign Selection & Ad Matching        │   │
│  └─────────────────────────────────────────┘   │
│                                                   │
│  ┌─────────────────────────────────────────┐   │
│  │  VAST XML Generator                      │   │
│  └─────────────────────────────────────────┘   │
│                                                   │
│  ┌─────────────────────────────────────────┐   │
│  │  Tracking & Analytics                    │   │
│  └─────────────────────────────────────────┘   │
└─────────────────┬───────────────────────────────┘
                  │
                  │ VAST XML Response
                  │
┌─────────────────▼───────────────────────────────┐
│              Video Player                        │
│  ┌──────────────────────────────────────────┐  │
│  │  Parse VAST, Load Video, Fire Trackers   │  │
│  └──────────────────────────────────────────┘  │
└──────────────────────────────────────────────────┘
```

## VAST 3.0 Support

Server ini mendukung VAST 3.0 standard dengan fitur:

- **InLine Ads**: Direct ad serving
- **Wrapper Ads**: Ad network integration
- **Linear Ads**: Video ads dengan timeline
- **Tracking Events**: Comprehensive event tracking
  - Impression
  - Start, FirstQuartile, Midpoint, ThirdQuartile, Complete
  - Click, ClickThrough
  - Pause, Resume
  - Mute, Unmute
  - Fullscreen, ExitFullscreen
  - Skip
  - Error handling
- **Skip Controls**: Configurable skip offset
- **Multiple MediaFiles**: Support untuk berbagai format & bitrate

## Testing

Run tests:
```bash
npm test
```

## Development

### Project Structure
```
clickterra/
├── src/
│   ├── controllers/      # Request handlers
│   │   ├── adServerController.js
│   │   ├── campaignController.js
│   │   └── creativeController.js
│   ├── models/          # Database models
│   │   └── database.js
│   ├── routes/          # API routes
│   │   ├── adserver.js
│   │   ├── campaigns.js
│   │   └── creatives.js
│   └── utils/           # Utilities
│       └── vastGenerator.js
├── public/              # Admin dashboard
│   └── index.html
├── data/                # SQLite database
│   └── adserver.db
├── server.js            # Main application
├── package.json
└── README.md
```

### Adding New Features

1. **Database Changes**: Update `src/models/database.js`
2. **Business Logic**: Add to appropriate controller
3. **API Endpoints**: Add routes in `src/routes/`
4. **UI Updates**: Modify `public/index.html`

## Security Considerations

- Validate all input data
- Sanitize URLs and user-generated content
- Implement rate limiting for production
- Use HTTPS in production
- Implement authentication for admin endpoints
- Regular security audits

## Performance Optimization

- Database indexing on frequently queried fields
- Response caching for VAST tags
- CDN for video creative hosting
- Gzip compression for API responses
- Connection pooling for database

## Troubleshooting

### Database locked error
Solution: Ensure only one instance is accessing the database

### VAST not loading in player
- Check CORS configuration
- Verify video URL is accessible
- Check browser console for errors
- Validate VAST XML syntax

### No ads serving
- Verify campaign is active
- Check campaign has creatives
- Review start/end dates
- Check budget limits

## Support & Documentation

- GitHub Issues: https://github.com/publisherad036/clickterra/issues
- VAST Specification: https://www.iab.com/guidelines/vast/

## License

MIT License

## Contributors

Built with ❤️ for digital advertising industry
