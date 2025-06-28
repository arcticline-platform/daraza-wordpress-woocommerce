# Changelog

## [1.2] - 2025-06-28

### Security Improvements

#### 🔒 **Major Security Enhancements**
- **Enhanced Input Validation**: Implemented strict validation for all user inputs
  - Phone number validation with regex pattern `/^[0-9]{10,14}$/`
  - Amount validation (1 to 1,000,000 range)
  - Reference and note length limits (100 and 255 characters respectively)
  - Enhanced sanitization using WordPress functions

- **CSRF Protection**: Added comprehensive nonce verification
  - Nonce protection for all form submissions
  - Unique nonce actions for different operations
  - AJAX request nonce verification
  - Admin page access nonce validation

- **API Key Security**: Upgraded encryption and management
  - Upgraded from AES-256-CBC to AES-256-GCM encryption
  - Proper IV management using WordPress salts
  - Authentication tags for integrity verification
  - 90-day key rotation policy
  - Secure API key display (masked in admin)

- **Rate Limiting**: Implemented request rate limiting
  - IP-based rate limiting for payment endpoints
  - 10 requests per 5 minutes for payment operations
  - 5 remittance requests per 5 minutes for admin users
  - Transient-based rate limit tracking

#### 🛡️ **SSL/TLS Security**
- Enforced SSL certificate verification for all API calls
- Updated to HTTP/1.1 for better security
- Added proper User-Agent headers
- Increased timeouts for payment operations (3 minutes)

#### 🚫 **XSS Protection**
- Consistent output escaping throughout the plugin
- Enhanced shortcode security with proper escaping
- JavaScript input validation and sanitization
- HTML attribute escaping
- External links with `rel="noopener noreferrer"`

#### 🔐 **Access Control**
- Enhanced capability checks for all admin functions
- Proper `current_user_can()` checks throughout
- Improved session management
- Secure admin page access validation

#### 📝 **Error Handling & Logging**
- Generic error messages to avoid information disclosure
- Proper exception handling with try-catch blocks
- Enhanced logging without sensitive data exposure
- WooCommerce logger integration with fallback

#### 🗄️ **Database Security**
- Encrypted storage for sensitive data
- Secure handling of WordPress options
- Protected order meta data handling

### Technical Improvements

#### 🔧 **Code Quality**
- Added proper ABSPATH checks
- Enhanced error handling throughout
- Improved code documentation
- Better separation of concerns

#### 🌐 **JavaScript Security**
- Enhanced frontend input validation
- Safe feature detection for WordPress/React components
- Improved error handling in React components
- Client-side input sanitization

#### 🔌 **WooCommerce Integration**
- Enhanced payment gateway security
- Secure refund processing
- Improved checkout field validation
- Better order meta handling

### Bug Fixes
- Fixed syntax errors in method descriptions
- Corrected nonce verification in order meta updates
- Improved asset enqueuing logic
- Enhanced shortcode output security

### Documentation
- Added comprehensive security documentation
- Created detailed changelog
- Updated plugin header information
- Added security best practices guide

### Compatibility
- WordPress 5.0+ compatibility
- PHP 7.4+ requirement
- WooCommerce 3.0+ compatibility
- Enhanced block checkout support

---

## [1.1] - Previous Version
- Initial WooCommerce integration
- Basic API integration
- Simple shortcode support

## [1.0] - Initial Release
- Basic Daraza Payments integration
- Simple admin interface
- Basic payment processing 