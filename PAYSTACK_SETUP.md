# Paystack Integration Setup

## Configuration

Paystack has been integrated into the API. The secret key has been added to `config.php`.

### Required Setup Steps:

1. **Add Paystack Public Key** (Optional but recommended for frontend)
   - Update `PAYSTACK_PUBLIC_KEY` in `config.php` with your Paystack public key
   - Public key format: `pk_test_...` (for test) or `pk_live_...` (for production)

2. **Run Database Migration**
   - Execute `db_payments.sql` to add payment tracking columns to the orders table
   - This adds `payment_reference` and `payment_status` columns
   - Optionally creates a `payments` table for detailed payment tracking

3. **Update Secret Key for Production**
   - Replace the test secret key with your live secret key when going to production
   - Format: `sk_live_...` (for production)

## API Endpoints

### Initialize Payment
**POST** `/user/payments.php`
- Creates a Paystack payment session for an order
- Returns authorization URL for redirecting user to Paystack checkout
- Stores payment reference in order record

### Verify Payment
**GET** `/user/verify_payment.php?reference=PAY_...`
- Verifies payment status with Paystack
- Updates order status to "Completed" if payment successful
- Updates user stats (total orders, total spent, loyalty points)

## Payment Flow

1. Create order via `/user/orders.php`
2. Initialize payment via `/user/payments.php` with order_id
3. Redirect user to `authorization_url` from response
4. User completes payment on Paystack
5. Verify payment via `/user/verify_payment.php` with payment reference
6. Order status automatically updates to "Completed" on successful payment

## Testing

Use Paystack test cards:
- **Success**: 4084084084084081
- **Declined**: 5060666666666666666
- **Insufficient Funds**: 5060666666666666667

CVV: Any 3 digits
Expiry: Any future date
PIN: 0000 (for card PIN)
OTP: 123456 (for OTP verification)

## Notes

- Payment amounts are automatically converted to kobo (NGN smallest unit) for Paystack
- Payment references are auto-generated in format: `PAY_{timestamp}_{order_id}_{random}`
- Failed payments can be retried by initializing a new payment for the same order
- Once an order is marked as "Completed", no further payments can be initialized

