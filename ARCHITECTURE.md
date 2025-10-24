# ClickTerra Video AdServer - Technical Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Client Applications                       │
│  ┌──────────────┐  ┌──────────────┐  ┌─────────────────────┐  │
│  │  Video.js    │  │  JW Player   │  │  Custom Players     │  │
│  │  with IMA    │  │              │  │  (VAST compliant)   │  │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬──────────┘  │
└─────────┼──────────────────┼─────────────────────┼─────────────┘
          │                  │                     │
          └──────────────────┴─────────────────────┘
                             │
                    VAST Request (HTTP GET)
                    /vast?placement=X&campaign_id=Y
                             │
┌────────────────────────────▼─────────────────────────────────────┐
│                   ClickTerra AdServer (Node.js/Express)          │
│                                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    API Layer (Express)                   │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │   │
│  │  │  Campaign    │  │  Creative    │  │  AdServer    │  │   │
│  │  │  Routes      │  │  Routes      │  │  Routes      │  │   │
│  │  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  │   │
│  └─────────┼──────────────────┼──────────────────┼──────────┘   │
│            │                  │                  │               │
│  ┌─────────▼──────────────────▼──────────────────▼──────────┐   │
│  │                    Controllers                            │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │   │
│  │  │  Campaign    │  │  Creative    │  │  AdServer    │  │   │
│  │  │  Controller  │  │  Controller  │  │  Controller  │  │   │
│  │  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  │   │
│  └─────────┼──────────────────┼──────────────────┼──────────┘   │
│            │                  │                  │               │
│  ┌─────────▼──────────────────▼──────────────────▼──────────┐   │
│  │                   Business Logic                          │   │
│  │  • Ad Selection Algorithm                                 │   │
│  │  • VAST XML Generation (vastGenerator.js)                │   │
│  │  • Tracking Event Processing                             │   │
│  │  • Statistics Aggregation                                │   │
│  └───────────────────────────┬───────────────────────────────┘   │
│                              │                                   │
│  ┌───────────────────────────▼───────────────────────────────┐   │
│  │                   Data Layer (SQLite)                      │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │   │
│  │  │  Campaigns   │  │  Creatives   │  │  Impressions │   │   │
│  │  └──────────────┘  └──────────────┘  └──────────────┘   │   │
│  │  ┌──────────────┐  ┌──────────────────────────────────┐ │   │
│  │  │  Requests    │  │  Tracking Events                 │ │   │
│  │  └──────────────┘  └──────────────────────────────────┘ │   │
│  └───────────────────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────────────────┘
                             │
                    VAST XML Response
                             │
┌────────────────────────────▼─────────────────────────────────────┐
│                       Video Player                                │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  1. Parse VAST XML                                        │   │
│  │  2. Load video creative from MediaFile URL               │   │
│  │  3. Display video ad                                     │   │
│  │  4. Fire tracking pixels:                                │   │
│  │     • Impression (on load)                               │   │
│  │     • Start (on play)                                    │   │
│  │     • Quartiles (25%, 50%, 75%)                          │   │
│  │     • Complete (100%)                                    │   │
│  │     • Click (on user click)                              │   │
│  │     • Skip, Pause, Mute, Fullscreen events               │   │
│  └──────────────────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────────────────┘
```

## Component Details

### 1. API Routes Layer

**Purpose**: Handle HTTP requests and route to appropriate controllers

**Files**:
- `src/routes/campaigns.js` - Campaign management endpoints
- `src/routes/creatives.js` - Creative management endpoints
- `src/routes/adserver.js` - Ad serving and tracking endpoints

**Responsibilities**:
- URL routing
- Request validation
- Response formatting

### 2. Controllers Layer

**Purpose**: Process business logic and orchestrate data operations

**Files**:
- `src/controllers/campaignController.js` - Campaign CRUD operations
- `src/controllers/creativeController.js` - Creative CRUD operations
- `src/controllers/adServerController.js` - Ad serving logic & tracking

**Key Functions**:

#### CampaignController
```javascript
- createCampaign()    // Create new campaign
- getAllCampaigns()   // List all campaigns
- getCampaign(id)     // Get campaign details
- updateCampaign(id)  // Update campaign
- deleteCampaign(id)  // Delete campaign
- getCampaignStats(id) // Get statistics
```

#### CreativeController
```javascript
- createCreative()    // Create new video creative
- getAllCreatives()   // List all creatives
- getCreative(id)     // Get creative details
- updateCreative(id)  // Update creative
- deleteCreative(id)  // Delete creative
- getCreativeStats(id) // Get statistics
```

#### AdServerController
```javascript
- serveVAST()         // Generate and serve VAST XML
- trackEvent()        // Process tracking events
- getStats()          // Get overall statistics
- getRecentRequests() // Get recent ad requests
- getRecentEvents()   // Get recent tracking events
```

### 3. Business Logic Layer

**Purpose**: Core ad serving and VAST generation logic

**Files**:
- `src/utils/vastGenerator.js` - VAST XML generation

**Key Functions**:

```javascript
VASTGenerator:
  - generateVAST(creative, requestId)
    // Generates VAST 3.0 XML for a video creative
    
  - generateTrackingEvents(creativeId, requestId)
    // Creates tracking pixel URLs for all events
    
  - formatDuration(seconds)
    // Converts seconds to HH:MM:SS format
    
  - generateVASTWrapper(vastTagUrl, adId)
    // Creates VAST wrapper for ad networks
```

### 4. Data Layer

**Purpose**: Database operations and schema management

**Files**:
- `src/models/database.js` - SQLite database wrapper

**Database Schema**:

```sql
campaigns
  - id (UUID)
  - name
  - advertiser
  - status (active/inactive)
  - start_date
  - end_date
  - daily_budget
  - total_budget
  - created_at

video_creatives
  - id (UUID)
  - campaign_id (FK)
  - title
  - description
  - duration (seconds)
  - video_url
  - video_type
  - bitrate
  - width
  - height
  - click_through_url
  - skip_offset
  - created_at

ad_impressions
  - id (auto-increment)
  - creative_id (FK)
  - campaign_id (FK)
  - event_type
  - ip_address
  - user_agent
  - timestamp

ad_requests
  - id (auto-increment)
  - campaign_id
  - creative_id
  - ip_address
  - user_agent
  - placement
  - timestamp
```

## Request Flow

### Ad Request Flow

```
1. Video Player → GET /vast?placement=homepage
                       ↓
2. AdServer receives request
                       ↓
3. AdServerController.serveVAST()
   - Parse query parameters
   - Select active campaign
   - Select creative (random or weighted)
   - Log ad request
                       ↓
4. VASTGenerator.generateVAST()
   - Create VAST XML structure
   - Add creative details
   - Generate tracking URLs
   - Add MediaFile information
                       ↓
5. Return VAST XML to player
                       ↓
6. Player parses VAST and plays ad
                       ↓
7. Player fires tracking pixels
   GET /track/impression/:creative_id/:request_id
   GET /track/start/:creative_id/:request_id
   GET /track/firstQuartile/:creative_id/:request_id
   ... etc
                       ↓
8. AdServerController.trackEvent()
   - Log event to database
   - Return 1x1 transparent GIF
```

### Campaign Management Flow

```
Admin Dashboard → POST /api/campaigns
                       ↓
CampaignController.createCampaign()
                       ↓
Database.run("INSERT INTO campaigns ...")
                       ↓
Return campaign object to dashboard
```

## VAST XML Structure

Generated VAST 3.0 XML structure:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<VAST version="3.0">
  <Ad id="creative-uuid">
    <InLine>
      <AdSystem>ClickTerra AdServer</AdSystem>
      <AdTitle><![CDATA[Creative Title]]></AdTitle>
      <Description><![CDATA[Creative Description]]></Description>
      <Error><![CDATA[error-tracking-url]]></Error>
      <Impression><![CDATA[impression-tracking-url]]></Impression>
      <Creatives>
        <Creative id="creative-uuid" sequence="1">
          <Linear skipoffset="00:00:05">
            <Duration>00:00:30</Duration>
            <TrackingEvents>
              <Tracking event="start"><![CDATA[url]]></Tracking>
              <Tracking event="firstQuartile"><![CDATA[url]]></Tracking>
              <Tracking event="midpoint"><![CDATA[url]]></Tracking>
              <Tracking event="thirdQuartile"><![CDATA[url]]></Tracking>
              <Tracking event="complete"><![CDATA[url]]></Tracking>
              <!-- Additional tracking events -->
            </TrackingEvents>
            <VideoClicks>
              <ClickThrough><![CDATA[landing-page-url]]></ClickThrough>
              <ClickTracking><![CDATA[click-tracking-url]]></ClickTracking>
            </VideoClicks>
            <MediaFiles>
              <MediaFile delivery="progressive" type="video/mp4" 
                         width="1920" height="1080" bitrate="1200">
                <![CDATA[video-file-url]]>
              </MediaFile>
            </MediaFiles>
          </Linear>
        </Creative>
      </Creatives>
    </InLine>
  </Ad>
</VAST>
```

## Tracking Events

Supported VAST tracking events:

| Event | Description | When Fired |
|-------|-------------|------------|
| impression | Ad loaded | When VAST is parsed |
| start | Video started | When video begins playing |
| firstQuartile | 25% complete | At 25% of video duration |
| midpoint | 50% complete | At 50% of video duration |
| thirdQuartile | 75% complete | At 75% of video duration |
| complete | 100% complete | When video finishes |
| click | User clicked ad | On click/tap |
| skip | User skipped ad | When skip button clicked |
| pause | Video paused | When user pauses |
| resume | Video resumed | When user resumes |
| mute | Audio muted | When user mutes |
| unmute | Audio unmuted | When user unmutes |
| fullscreen | Entered fullscreen | When fullscreen enabled |
| exitFullscreen | Exited fullscreen | When fullscreen disabled |
| error | Error occurred | On any error |

## Performance Considerations

### Database Optimization
- Indexes on frequently queried fields (campaign_id, creative_id)
- Connection pooling for concurrent requests
- Prepared statements for security

### Caching Strategy
- VAST responses can be cached (short TTL)
- Campaign data cached in memory
- Statistics calculated on-demand or cached

### Scalability
- Stateless design allows horizontal scaling
- Load balancer can distribute requests
- Database can be migrated to PostgreSQL/MySQL for larger scale
- Redis can be added for caching layer

### Security
- Input validation on all endpoints
- SQL injection prevention (parameterized queries)
- XSS prevention in admin dashboard
- Rate limiting recommended for production
- HTTPS required for production

## Admin Dashboard

Web interface at `http://localhost:3000/`

**Features**:
- Real-time statistics dashboard
- Campaign CRUD interface
- Creative CRUD interface
- VAST tag generator
- Responsive design
- No authentication (add for production)

**Technology**:
- Vanilla JavaScript
- Fetch API for AJAX
- CSS Grid/Flexbox layout
- No external frameworks (lightweight)

## Testing Strategy

### Unit Tests (Recommended)
```javascript
- Campaign controller tests
- Creative controller tests
- VAST generator tests
- Database operations tests
```

### Integration Tests (Recommended)
```javascript
- API endpoint tests
- End-to-end ad serving flow
- Tracking event processing
```

### Manual Testing
```bash
# Start server
npm start

# Run sample data setup
npm run setup-sample

# Test VAST endpoint
curl "http://localhost:3000/vast?placement=test"

# Test tracking
curl "http://localhost:3000/track/impression/creative-id/request-id"
```

## Deployment

### Development
```bash
npm install
npm start
```

### Production (Recommended Setup)
```bash
# Use PM2 for process management
pm2 start server.js --name clickterra-adserver

# Use Nginx as reverse proxy
# Enable HTTPS with Let's Encrypt
# Configure environment variables
# Setup database backups
# Monitor logs
```

### Environment Variables
```env
PORT=3000
NODE_ENV=production
DB_PATH=/var/lib/clickterra/adserver.db
SERVER_URL=https://your-domain.com
```

## Future Enhancements

Potential improvements:
- [ ] User authentication & authorization
- [ ] Advanced targeting (geo, device, time)
- [ ] Frequency capping
- [ ] A/B testing support
- [ ] Real-time bidding (RTB) integration
- [ ] Video player SDK
- [ ] Analytics dashboard with charts
- [ ] Export reports (CSV, PDF)
- [ ] Multi-tenant support
- [ ] API rate limiting
- [ ] Video transcoding service
- [ ] CDN integration
- [ ] Programmatic buying support

## Support

For technical questions or issues:
- Check documentation: README.md, QUICKSTART.md
- Review examples: EXAMPLES.md
- GitHub Issues: https://github.com/publisherad036/clickterra/issues
