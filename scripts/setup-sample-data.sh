#!/bin/bash

# ClickTerra Video AdServer - Sample Data Setup Script
# This script creates sample campaigns and creatives for testing

echo "=================================================="
echo "ClickTerra Video AdServer - Sample Data Setup"
echo "=================================================="
echo ""

# Check if server is running
if ! curl -s http://localhost:3000/health > /dev/null 2>&1; then
    echo "❌ Error: Server is not running!"
    echo "Please start the server first: npm start"
    exit 1
fi

echo "✓ Server is running"
echo ""

# Create Campaign 1
echo "Creating campaign: Summer Video Campaign 2024..."
CAMPAIGN1_RESPONSE=$(curl -s -X POST http://localhost:3000/api/campaigns \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Summer Video Campaign 2024",
    "advertiser": "Example Corp",
    "start_date": "2024-06-01",
    "end_date": "2024-08-31",
    "daily_budget": 1000,
    "total_budget": 90000
  }')

CAMPAIGN1_ID=$(echo $CAMPAIGN1_RESPONSE | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

if [ -z "$CAMPAIGN1_ID" ]; then
    echo "❌ Error creating campaign 1"
    echo "Response: $CAMPAIGN1_RESPONSE"
    exit 1
fi

echo "✓ Campaign created: $CAMPAIGN1_ID"

# Create Campaign 2
echo "Creating campaign: Winter Holiday Campaign 2024..."
CAMPAIGN2_RESPONSE=$(curl -s -X POST http://localhost:3000/api/campaigns \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Winter Holiday Campaign 2024",
    "advertiser": "Brand X",
    "start_date": "2024-12-01",
    "end_date": "2024-12-31",
    "daily_budget": 2000,
    "total_budget": 60000
  }')

CAMPAIGN2_ID=$(echo $CAMPAIGN2_RESPONSE | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

if [ -z "$CAMPAIGN2_ID" ]; then
    echo "❌ Error creating campaign 2"
    exit 1
fi

echo "✓ Campaign created: $CAMPAIGN2_ID"
echo ""

# Create Creatives for Campaign 1
echo "Creating video creatives for Campaign 1..."

# Creative 1 - 30 second ad
CREATIVE1_RESPONSE=$(curl -s -X POST http://localhost:3000/api/creatives \
  -H "Content-Type: application/json" \
  -d "{
    \"campaign_id\": \"$CAMPAIGN1_ID\",
    \"title\": \"Product Launch Video - 30s\",
    \"description\": \"Exciting new product announcement\",
    \"duration\": 30,
    \"video_url\": \"https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4\",
    \"video_type\": \"video/mp4\",
    \"bitrate\": 1200,
    \"width\": 1920,
    \"height\": 1080,
    \"click_through_url\": \"https://example.com/products/new-launch\",
    \"skip_offset\": 5
  }")

CREATIVE1_ID=$(echo $CREATIVE1_RESPONSE | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
echo "✓ Creative created: Product Launch Video - 30s ($CREATIVE1_ID)"

# Creative 2 - 15 second ad
CREATIVE2_RESPONSE=$(curl -s -X POST http://localhost:3000/api/creatives \
  -H "Content-Type: application/json" \
  -d "{
    \"campaign_id\": \"$CAMPAIGN1_ID\",
    \"title\": \"Brand Awareness - 15s\",
    \"description\": \"Quick brand message\",
    \"duration\": 15,
    \"video_url\": \"https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4\",
    \"video_type\": \"video/mp4\",
    \"bitrate\": 1000,
    \"width\": 1280,
    \"height\": 720,
    \"click_through_url\": \"https://example.com/about\",
    \"skip_offset\": 5
  }")

CREATIVE2_ID=$(echo $CREATIVE2_RESPONSE | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
echo "✓ Creative created: Brand Awareness - 15s ($CREATIVE2_ID)"

# Create Creatives for Campaign 2
echo ""
echo "Creating video creatives for Campaign 2..."

# Creative 3 - Holiday ad
CREATIVE3_RESPONSE=$(curl -s -X POST http://localhost:3000/api/creatives \
  -H "Content-Type: application/json" \
  -d "{
    \"campaign_id\": \"$CAMPAIGN2_ID\",
    \"title\": \"Holiday Special Offer\",
    \"description\": \"Limited time holiday deals\",
    \"duration\": 20,
    \"video_url\": \"https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerJoyrides.mp4\",
    \"video_type\": \"video/mp4\",
    \"bitrate\": 1200,
    \"width\": 1920,
    \"height\": 1080,
    \"click_through_url\": \"https://example.com/holiday-sale\",
    \"skip_offset\": 5
  }")

CREATIVE3_ID=$(echo $CREATIVE3_RESPONSE | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
echo "✓ Creative created: Holiday Special Offer ($CREATIVE3_ID)"

echo ""
echo "=================================================="
echo "✓ Sample data setup complete!"
echo "=================================================="
echo ""
echo "Created:"
echo "  - 2 campaigns"
echo "  - 3 video creatives"
echo ""
echo "Next steps:"
echo "  1. Visit admin dashboard: http://localhost:3000"
echo "  2. Test VAST endpoint: http://localhost:3000/vast?placement=test"
echo "  3. View campaign 1 VAST: http://localhost:3000/vast?campaign_id=$CAMPAIGN1_ID"
echo "  4. View campaign 2 VAST: http://localhost:3000/vast?campaign_id=$CAMPAIGN2_ID"
echo ""
echo "Test video player: http://localhost:3000/test-player.html"
echo ""
