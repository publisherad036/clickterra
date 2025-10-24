const { v4: uuidv4 } = require('uuid');

class VASTGenerator {
  constructor(serverUrl) {
    this.serverUrl = serverUrl || process.env.SERVER_URL || 'http://localhost:3000';
  }

  /**
   * Generate VAST 3.0 XML for a video creative
   * @param {Object} creative - Video creative object
   * @param {String} requestId - Unique request ID for tracking
   * @returns {String} VAST XML
   */
  generateVAST(creative, requestId = uuidv4()) {
    const impressionUrl = `${this.serverUrl}/track/impression/${creative.id}/${requestId}`;
    const clickTrackingUrl = `${this.serverUrl}/track/click/${creative.id}/${requestId}`;
    const errorUrl = `${this.serverUrl}/track/error/${creative.id}/${requestId}`;
    
    const trackingEvents = this.generateTrackingEvents(creative.id, requestId);
    
    const vast = `<?xml version="1.0" encoding="UTF-8"?>
<VAST version="3.0">
  <Ad id="${creative.id}">
    <InLine>
      <AdSystem>ClickTerra AdServer</AdSystem>
      <AdTitle><![CDATA[${creative.title}]]></AdTitle>
      <Description><![CDATA[${creative.description || ''}]]></Description>
      <Error><![CDATA[${errorUrl}]]></Error>
      <Impression><![CDATA[${impressionUrl}]]></Impression>
      <Creatives>
        <Creative id="${creative.id}" sequence="1">
          <Linear skipoffset="${this.formatSkipOffset(creative.skip_offset)}">
            <Duration>${this.formatDuration(creative.duration)}</Duration>
            <TrackingEvents>
${trackingEvents}
            </TrackingEvents>
            <VideoClicks>
              <ClickThrough><![CDATA[${creative.click_through_url || ''}]]></ClickThrough>
              <ClickTracking><![CDATA[${clickTrackingUrl}]]></ClickTracking>
            </VideoClicks>
            <MediaFiles>
              <MediaFile delivery="progressive" type="${creative.video_type}" width="${creative.width}" height="${creative.height}" bitrate="${creative.bitrate || 800}">
                <![CDATA[${creative.video_url}]]>
              </MediaFile>
            </MediaFiles>
          </Linear>
        </Creative>
      </Creatives>
    </InLine>
  </Ad>
</VAST>`;
    
    return vast;
  }

  /**
   * Generate tracking events for VAST
   * @param {String} creativeId - Creative ID
   * @param {String} requestId - Request ID
   * @returns {String} Tracking events XML
   */
  generateTrackingEvents(creativeId, requestId) {
    const events = [
      'start', 'firstQuartile', 'midpoint', 'thirdQuartile', 
      'complete', 'pause', 'resume', 'mute', 'unmute', 
      'fullscreen', 'exitFullscreen', 'skip'
    ];
    
    return events.map(event => {
      const url = `${this.serverUrl}/track/${event}/${creativeId}/${requestId}`;
      return `              <Tracking event="${event}"><![CDATA[${url}]]></Tracking>`;
    }).join('\n');
  }

  /**
   * Format duration in HH:MM:SS format
   * @param {Number} seconds - Duration in seconds
   * @returns {String} Formatted duration
   */
  formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  }

  /**
   * Format skip offset
   * @param {Number} seconds - Skip offset in seconds
   * @returns {String} Formatted skip offset
   */
  formatSkipOffset(seconds) {
    if (!seconds) return '00:00:05';
    return this.formatDuration(seconds);
  }

  /**
   * Generate VAST wrapper (for ad networks)
   * @param {String} vastTagUrl - URL to wrapped VAST
   * @param {String} adId - Ad ID
   * @returns {String} VAST wrapper XML
   */
  generateVASTWrapper(vastTagUrl, adId = uuidv4()) {
    const errorUrl = `${this.serverUrl}/track/error/wrapper/${adId}`;
    const impressionUrl = `${this.serverUrl}/track/impression/wrapper/${adId}`;
    
    return `<?xml version="1.0" encoding="UTF-8"?>
<VAST version="3.0">
  <Ad id="${adId}">
    <Wrapper>
      <AdSystem>ClickTerra AdServer</AdSystem>
      <VASTAdTagURI><![CDATA[${vastTagUrl}]]></VASTAdTagURI>
      <Error><![CDATA[${errorUrl}]]></Error>
      <Impression><![CDATA[${impressionUrl}]]></Impression>
      <Creatives>
        <Creative>
          <Linear>
            <TrackingEvents>
            </TrackingEvents>
          </Linear>
        </Creative>
      </Creatives>
    </Wrapper>
  </Ad>
</VAST>`;
  }
}

module.exports = VASTGenerator;
