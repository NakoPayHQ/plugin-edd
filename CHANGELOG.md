# Changelog
## 0.2.0 - 2026-05-17

### Changed
- Default API base URL is now https://api.nakopay.com/v1/ (branded primary). The Supabase functions URL remains as documented fallback constant.

## 0.1.0 - 2026-05-02

### Added
- Initial release
- EDD payment gateway registration
- NakoPay hosted checkout redirect
- HMAC-SHA256 webhook signature verification
- Automatic payment status updates (paid, expired, canceled)
- Test/live mode support
- Transaction ID storage on EDD payment meta
