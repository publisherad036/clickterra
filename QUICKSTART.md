# Quick Start Guide - ClickTerra Video AdServer

Panduan cepat untuk memulai menggunakan ClickTerra Video AdServer.

## Setup Cepat (5 Menit)

### 1. Install Dependencies

```bash
npm install
```

### 2. Setup Environment

```bash
cp .env.example .env
```

File `.env` sudah dikonfigurasi dengan default values yang siap pakai.

### 3. Start Server

```bash
npm start
```

Server akan berjalan di: http://localhost:3000

## Cara Menggunakan

### Step 1: Buka Admin Dashboard

Buka browser dan akses: http://localhost:3000

Anda akan melihat dashboard dengan statistik dan management interface.

### Step 2: Buat Campaign

1. Klik tombol **"+ Create Campaign"**
2. Isi form:
   - **Campaign Name**: Nama campaign Anda (contoh: "Summer Sale 2024")
   - **Advertiser**: Nama advertiser (contoh: "Brand X")
   - **Start/End Date**: (opsional) periode campaign
   - **Budget**: (opsional) budget harian atau total
3. Klik **"Create Campaign"**

### Step 3: Buat Video Creative

1. Klik tombol **"+ Create Creative"**
2. Isi form:
   - **Campaign**: Pilih campaign yang sudah dibuat
   - **Title**: Judul video ad (contoh: "Product Launch Video")
   - **Description**: Deskripsi (opsional)
   - **Duration**: Durasi video dalam detik (contoh: 30)
   - **Video URL**: URL video MP4 Anda
   - **Click Through URL**: Landing page URL
   - **Width/Height**: Resolusi video (default: 1280x720)
   - **Skip Offset**: Waktu sebelum tombol skip muncul (default: 5 detik)
3. Klik **"Create Creative"**

### Step 4: Gunakan VAST URL

Setelah creative dibuat, gunakan VAST URL ini di video player:

```
http://localhost:3000/vast?placement=YOUR_PLACEMENT_ID
```

Atau untuk campaign tertentu:

```
http://localhost:3000/vast?placement=YOUR_PLACEMENT_ID&campaign_id=CAMPAIGN_ID
```

## Testing Cepat

### Test dengan cURL

```bash
# 1. Buat campaign
curl -X POST http://localhost:3000/api/campaigns \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Campaign",
    "advertiser": "Test Advertiser"
  }'

# Simpan campaign_id dari response

# 2. Buat creative
curl -X POST http://localhost:3000/api/creatives \
  -H "Content-Type: application/json" \
  -d '{
    "campaign_id": "CAMPAIGN_ID_DARI_STEP_1",
    "title": "Test Video Ad",
    "duration": 30,
    "video_url": "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4",
    "click_through_url": "https://example.com",
    "width": 1280,
    "height": 720
  }'

# 3. Request VAST tag
curl "http://localhost:3000/vast?placement=test"
```

### Test dengan Browser

1. Buka: http://localhost:3000/test-player.html
2. Lihat console browser untuk VAST XML
3. Video player akan menampilkan sample video

## Integrasi dengan Video Player

### Video.js + IMA

```html
<video id="player" class="video-js" controls></video>

<script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
<script src="//imasdk.googleapis.com/js/sdkloader/ima3.js"></script>
<script src="videojs-ima.js"></script>

<script>
  var player = videojs('player');
  player.ima({
    adTagUrl: 'http://localhost:3000/vast?placement=homepage'
  });
</script>
```

### JW Player

```html
<div id="player"></div>

<script src="https://cdn.jwplayer.com/libraries/YOUR_KEY.js"></script>
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
```

## Fitur-Fitur Utama

### ✅ VAST 3.0 Support
- InLine ads
- Linear video ads
- Multiple tracking events
- Skip controls
- Click tracking

### ✅ Campaign Management
- Multiple campaigns
- Budget tracking
- Scheduling
- Status management

### ✅ Video Creative Management
- Multiple creatives per campaign
- Various video formats
- Configurable properties
- Click-through URLs

### ✅ Analytics & Tracking
- Impressions
- Video quartile tracking (25%, 50%, 75%, 100%)
- Click tracking
- CTR calculation
- Completion rate
- Real-time statistics

### ✅ Admin Dashboard
- Web-based interface
- Live statistics
- Easy campaign/creative management
- VAST tag generator

## API Endpoints

### Campaigns
- `POST /api/campaigns` - Create campaign
- `GET /api/campaigns` - List campaigns
- `GET /api/campaigns/:id` - Get campaign
- `PUT /api/campaigns/:id` - Update campaign
- `DELETE /api/campaigns/:id` - Delete campaign
- `GET /api/campaigns/:id/stats` - Campaign statistics

### Creatives
- `POST /api/creatives` - Create creative
- `GET /api/creatives` - List creatives
- `GET /api/creatives/:id` - Get creative
- `PUT /api/creatives/:id` - Update creative
- `DELETE /api/creatives/:id` - Delete creative
- `GET /api/creatives/:id/stats` - Creative statistics

### Ad Serving
- `GET /vast` - Get VAST tag
- `GET /track/:event/:creative_id/:request_id` - Track event
- `GET /api/adserver/stats` - Overall statistics

## Troubleshooting

### Server tidak start
- Pastikan Node.js sudah terinstall: `node --version`
- Pastikan port 3000 tidak digunakan
- Check logs untuk error messages

### VAST tidak muncul di player
- Pastikan campaign status = "active"
- Pastikan ada creative yang terhubung dengan campaign
- Check CORS settings jika player di domain berbeda
- Validate VAST XML dengan VAST validator

### Tracking tidak bekerja
- Pastikan tracking URLs dalam VAST XML benar
- Check network tab di browser developer tools
- Pastikan video player mendukung VAST tracking

### Database error
- Pastikan folder `data/` ada dan writable
- Delete database file untuk reset: `rm data/adserver.db`
- Server akan recreate database saat restart

## Production Deployment

### Environment Variables

Untuk production, update `.env`:

```env
PORT=3000
NODE_ENV=production
DB_PATH=./data/adserver.db
SERVER_URL=https://your-domain.com
```

### Security Checklist

- [ ] Enable HTTPS
- [ ] Add authentication untuk admin endpoints
- [ ] Implement rate limiting
- [ ] Setup CORS properly
- [ ] Backup database regularly
- [ ] Monitor server logs
- [ ] Use CDN untuk video hosting
- [ ] Implement caching strategy

### Recommended Stack

- **Server**: Ubuntu 20.04+
- **Process Manager**: PM2
- **Reverse Proxy**: Nginx
- **SSL**: Let's Encrypt
- **Monitoring**: PM2 + LogTail

## Support

Untuk bantuan lebih lanjut:
- Baca dokumentasi lengkap: `README.md`
- Lihat contoh API usage: `EXAMPLES.md`
- GitHub Issues: https://github.com/publisherad036/clickterra/issues

## Next Steps

1. ✅ Setup server (selesai!)
2. 🎯 Buat campaign pertama
3. 📹 Upload video creative
4. 🔗 Integrate dengan video player
5. 📊 Monitor statistics
6. 🚀 Deploy ke production

Selamat menggunakan ClickTerra Video AdServer! 🎬