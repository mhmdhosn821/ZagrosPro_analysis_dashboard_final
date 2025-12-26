# 🚀 Zagros Ultimate Dashboard - WordPress Plugin

A powerful, single-file WordPress plugin that provides native Google Analytics 4 integration with real-time data visualization and Microsoft Clarity embedding - all wrapped in a stunning glassmorphism design.

## ✨ Key Features

### 🎯 Hybrid Analytics Approach
- **Native GA4 Integration**: Fetch raw data directly from Google Analytics 4 Data API (server-to-server)
- **Custom Chart Rendering**: Beautiful Chart.js visualizations with no iframes
- **Clarity Integration**: Microsoft Clarity dashboard embedded via iframe with unified design
- **Real-time Data**: Live active user counts with pulsing green indicator
- **Historical Analytics**: 30-day trend analysis with interactive charts

### 🎨 Glassmorphism Design System
- **Dark/Glass Theme**: Modern glass-effect cards with backdrop blur
- **Responsive Grid Layout**: Adapts to all screen sizes
- **Smooth Animations**: Hover effects and pulsing indicators
- **Persian Font Support**: Vazir font loaded from CDN
- **Professional UI**: Neon blue accents and smooth transitions

### 🔒 Security & Performance
- **Pure PHP Authentication**: Google Service Account auth without external libraries
- **JWT Token Generation**: Handcrafted JWT with OpenSSL signing
- **Smart Caching**: 2-minute cache for realtime data, 1-hour for historical
- **Input Validation**: Secure sanitization of all settings
- **Error Handling**: Graceful error messages instead of crashes

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- OpenSSL extension enabled
- Google Analytics 4 property
- Google Cloud Service Account with GA4 access
- (Optional) Microsoft Clarity account

## 📥 Installation

### Method 1: Direct Upload

1. Download `zagros-ultimate-dashboard.php`
2. Go to your WordPress installation directory
3. Navigate to `/wp-content/plugins/`
4. Create a new folder: `zagros-ultimate-dashboard`
5. Upload the PHP file to this folder
6. Go to WordPress Admin > Plugins
7. Activate "Zagros Ultimate Dashboard"

### Method 2: ZIP Upload

1. Create a folder named `zagros-ultimate-dashboard`
2. Place `zagros-ultimate-dashboard.php` inside
3. Zip the folder
4. Go to WordPress Admin > Plugins > Add New
5. Click "Upload Plugin"
6. Upload the ZIP file and activate

## ⚙️ Configuration

### Step 1: Set Up Google Cloud Service Account

1. **Create a Service Account**:
   - Go to [Google Cloud Console](https://console.cloud.google.com)
   - Navigate to "IAM & Admin" > "Service Accounts"
   - Click "Create Service Account"
   - Give it a name (e.g., "WordPress GA4 Reader")
   - Click "Create and Continue"

2. **Create JSON Key**:
   - Select your service account
   - Go to "Keys" tab
   - Click "Add Key" > "Create new key"
   - Choose "JSON" format
   - Download the JSON file

3. **Grant GA4 Access**:
   - Go to your [Google Analytics 4 Property](https://analytics.google.com)
   - Click "Admin" (bottom left)
   - Under "Property", click "Property Access Management"
   - Click the "+" button to add users
   - Enter the service account email (from JSON: `client_email`)
   - Assign "Viewer" role
   - Click "Add"

### Step 2: Get Your GA4 Property ID

1. Go to Google Analytics 4
2. Click "Admin" (bottom left)
3. Under "Property", click "Property Settings"
4. Your Property ID is displayed at the top (numeric value, e.g., `123456789`)

### Step 3: (Optional) Get Microsoft Clarity URL

1. Go to [Microsoft Clarity](https://clarity.microsoft.com)
2. Select your project
3. Click "Settings" > "Share"
4. Copy the shareable dashboard URL

### Step 4: Configure Plugin Settings

1. Go to WordPress Admin
2. Click "Zagros Dashboard" in the sidebar
3. Click "Settings" submenu
4. Enter your **GA4 Property ID**
5. Paste the entire **Service Account JSON** content
6. (Optional) Paste your **Clarity Embed URL**
7. Click "Save Settings"

## 🎯 Usage

### Dashboard Access

After activation and configuration, you'll see a new menu item:

**"Zagros Dashboard"** (⚡ Analytics icon)

The dashboard displays:

#### Top Row: Key Metrics (Glass Cards)
1. **Active Users (Realtime)** - Live count with pulsing green dot
2. **Total Sessions (30 Days)** - Sum of all sessions
3. **Total Users (30 Days)** - Total unique users

#### Middle Row: Chart
- **Daily Sessions Trend** - Interactive line chart showing 30-day history
- Smooth neon blue line with gradient fill
- Hover tooltips for detailed data
- Responsive design

#### Bottom Row: Clarity Dashboard
- **Microsoft Clarity Live** - Full embedded dashboard
- 800px height for optimal viewing
- Sandboxed iframe for security

## 🔧 Technical Details

### Architecture

#### Google Authentication Class (`Zagros_Google_Auth`)
- **JWT Generation**: Creates JSON Web Token with RS256 algorithm
- **OpenSSL Signing**: Signs JWT with private key from service account
- **Token Management**: Automatic refresh before expiry
- **Error Handling**: Detailed exception messages

#### GA4 Data Fetcher (`Zagros_GA4_Data`)
- **Realtime API**: Fetches active users from `runRealtimeReport` endpoint
- **Historical API**: Fetches sessions and users from `runReport` endpoint
- **Caching Strategy**:
  - Realtime: 2 minutes (WordPress transient)
  - Historical: 1 hour (WordPress transient)
- **Date Range**: Last 30 days with daily breakdown

#### Main Plugin Class (`Zagros_Ultimate_Dashboard`)
- **Singleton Pattern**: Single instance management
- **WordPress Hooks**: Proper use of actions and filters
- **Asset Management**: CDN-based Chart.js and Vazir font
- **Settings API**: Native WordPress settings registration

### API Endpoints Used

1. **Google OAuth2**: `https://oauth2.googleapis.com/token`
2. **GA4 Realtime**: `https://analyticsdata.googleapis.com/v1beta/properties/{PROPERTY_ID}:runRealtimeReport`
3. **GA4 Reports**: `https://analyticsdata.googleapis.com/v1beta/properties/{PROPERTY_ID}:runReport`

### Caching Keys

- `zagros_ga4_realtime_users` - Realtime active users (120 seconds)
- `zagros_ga4_historical_data` - 30-day historical data (3600 seconds)

### Security Features

- **Nonce Verification**: All form submissions protected
- **Input Sanitization**: `sanitize_text_field()`, `esc_url_raw()`, `esc_textarea()`
- **Output Escaping**: `esc_html()`, `esc_attr()`, `esc_url()`
- **Capability Checks**: `manage_options` required for all pages
- **Direct Access Prevention**: `ABSPATH` constant check
- **Iframe Sandboxing**: Restricted permissions for Clarity embed

## 🎨 Customization

### Modify Colors

Edit the inline styles in the `add_inline_styles()` method:

```php
// Primary accent color (currently neon blue)
color: #00e6ff;

// Background gradient
background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);

// Glass card effect
background: rgba(255, 255, 255, 0.05);
backdrop-filter: blur(10px);
```

### Adjust Cache Duration

Modify the transient expiry times:

```php
// In get_realtime_users() - currently 120 seconds
set_transient($cache_key, $active_users, 120);

// In get_historical_data() - currently 3600 seconds
set_transient($cache_key, $result, 3600);
```

### Change Chart Style

Modify the Chart.js configuration in `add_inline_scripts()`:

```javascript
borderColor: '#00e6ff',  // Line color
borderWidth: 3,          // Line thickness
tension: 0.4,            // Curve smoothness
```

### Adjust Historical Date Range

In the `get_historical_data()` method:

```php
'dateRanges' => array(
    array(
        'startDate' => '30daysAgo',  // Change this
        'endDate' => 'today'
    )
)
```

## 🐛 Troubleshooting

### "Configuration Required" Error

**Problem**: Settings page shows error about missing configuration.

**Solution**: 
1. Ensure GA4 Property ID is entered (numeric value only)
2. Ensure Service Account JSON is valid JSON format
3. Click "Save Settings" after entering values

### "Authentication Setup Failed" Error

**Problem**: Service Account JSON is invalid.

**Solution**:
1. Re-download the JSON key from Google Cloud Console
2. Copy the ENTIRE contents (should start with `{` and end with `}`)
3. Paste exactly as-is into the textarea
4. Ensure no extra characters or line breaks

### "Failed to Fetch GA4 Data" Error

**Problem**: Cannot retrieve data from Google Analytics.

**Solution**:
1. Verify the service account email is added to GA4 property with "Viewer" role
2. Check that the Property ID matches your GA4 property (Admin > Property Settings)
3. Wait a few minutes for permissions to propagate
4. Clear cache: Save settings again

### No Data Showing / Zero Values

**Problem**: Metrics show 0 or chart is empty.

**Solution**:
1. Ensure your GA4 property is collecting data
2. Check date range - plugin shows last 30 days
3. Verify realtime reporting is enabled in GA4
4. Allow 24-48 hours for historical data to populate

### Clarity Iframe Not Loading

**Problem**: Clarity section shows error or blank.

**Solution**:
1. Get the shareable link from Clarity (Settings > Share)
2. Use the full URL including `https://`
3. Ensure the dashboard is set to "Public" or "Shared" in Clarity settings

### PHP OpenSSL Errors

**Problem**: "Failed to sign data" or OpenSSL errors.

**Solution**:
1. Verify OpenSSL extension is enabled: `php -m | grep openssl`
2. Check PHP version is 7.4 or higher: `php -v`
3. Contact your hosting provider to enable OpenSSL

## 🔒 Security Best Practices

1. **Protect Service Account JSON**:
   - Never commit to version control
   - Store in WordPress database only (encrypted recommended)
   - Rotate keys periodically

2. **Limit Permissions**:
   - Only grant "Viewer" role to service account
   - Don't use owner/admin accounts

3. **Monitor Access**:
   - Check Google Cloud Console for unusual activity
   - Review WordPress admin access logs

4. **Keep Updated**:
   - Update WordPress regularly
   - Monitor security patches

## 📊 Data & Privacy

- **No User Data Collection**: Plugin only displays aggregate analytics
- **Server-Side Processing**: All API calls from server, not browser
- **Caching**: Reduces API calls and improves performance
- **No External Scripts**: Chart.js and fonts from trusted CDNs only
- **GDPR Compliant**: Displays only aggregated, anonymous data

## 🆘 Support

### Common Questions

**Q: Can I use this with Universal Analytics (GA3)?**  
A: No, this plugin is designed specifically for Google Analytics 4 (GA4).

**Q: Do I need a Google Cloud account?**  
A: Yes, you need to create a service account in Google Cloud Console (free tier available).

**Q: Can I customize the metrics shown?**  
A: Yes, you can modify the GA4 API requests in the `Zagros_GA4_Data` class to fetch different metrics.

**Q: Is Chart.js dependency safe?**  
A: Yes, Chart.js is loaded from the official CDN (jsDelivr) and is a widely trusted library.

**Q: Can I use this on multiple sites?**  
A: Yes, just install on each site and configure with your GA4 credentials.

## 📝 Changelog

### Version 1.0.0 (2024)
- Initial release
- Native GA4 Data API integration
- Google Service Account authentication (pure PHP)
- Realtime and historical data fetching
- Chart.js visualization
- Microsoft Clarity iframe integration
- Glassmorphism UI design
- Smart caching system
- Comprehensive error handling

## 📄 License

This plugin is licensed under GPL v2 or later.

## 👨‍💻 Author

**Zagros Pro**  
GitHub: [@mhmdhosn821](https://github.com/mhmdhosn821)

## 🙏 Credits

- **Chart.js**: [chartjs.org](https://www.chartjs.org)
- **Vazir Font**: [rastikerdar/vazir-font](https://github.com/rastikerdar/vazir-font)
- **Google Analytics 4**: [Google Analytics](https://analytics.google.com)
- **Microsoft Clarity**: [clarity.microsoft.com](https://clarity.microsoft.com)

---

**Made with ❤️ for the WordPress community**
