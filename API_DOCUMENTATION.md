# JSocialogs API Documentation

## Base URL
- Admin APIs: `/admin/`
- User/Public APIs: `/user/`

## Authentication
Admin endpoints require authentication via session (use `/admin/login.php` first).

---

## Admin APIs

### Authentication

#### POST /admin/login.php
Login as admin.
**Body:**
```json
{
  "username": "admin@jsocialogs.com",
  "password": "password"
}
```

#### POST /admin/logout.php
Logout admin.

---

### Products

#### GET /admin/allproducts.php
Get all products (admin view with decrypted passwords).
**Query Params:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 5)
- `search` (optional): Search in name/category

#### POST /admin/createproduct.php
Create a new product with accounts.
**Body:**
```json
{
  "name": "Product Name",
  "category": "Category",
  "followers": 1000,
  "price": 29.99,
  "tutorialLink": "https://...",
  "status": "Active",
  "accounts": [
    {"username": "user1", "password": "pass1", "status": "Active"},
    {"username": "user2", "password": "pass2"}
  ]
}
```

#### PUT /admin/editproduct.php
Update a product.
**Body:**
```json
{
  "product_id": 1,
  "name": "Updated Name",
  "category": "Category",
  "followers": 2000,
  "price": 39.99,
  "tutorialLink": "https://...",
  "status": "Active",
  "accounts": [
    {"username": "user1", "password": "newpass1"}
  ]
}
```

#### DELETE /admin/deleteproduct.php
Delete a product.
**Query Params:**
- `product_id`: Product ID to delete

---

### Users Management

#### GET /admin/users.php
Get all users or single user.
**Query Params:**
- `id` (optional): User ID for single user
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `search` (optional): Search in first_name, last_name, email, phone

#### POST /admin/users.php
Create a new user.
**Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "1234567890",
  "location": "City",
  "joined_at": "2024-01-01",
  "membership": "Member",
  "total_orders": 0,
  "total_spent": 0.00,
  "loyalty_points": 0
}
```

#### PUT /admin/users.php
Update a user.
**Query Params:**
- `id`: User ID (or include in body)
**Body:**
```json
{
  "id": 1,
  "first_name": "John",
  "email": "john@example.com",
  "membership": "Premium",
  "loyalty_points": 100
}
```

#### DELETE /admin/users.php
Delete a user.
**Query Params:**
- `id`: User ID to delete

---

### Orders Management

#### GET /admin/orders.php
Get all orders or single order.
**Query Params:**
- `id` (optional): Order ID for single order
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `search` (optional): Search in customer_name, customer_email, product name
- `status` (optional): Filter by status (Pending, Completed, Cancelled)

#### PUT /admin/orders.php
Update order (mainly status).
**Query Params:**
- `id`: Order ID (or include in body)
**Body:**
```json
{
  "id": 1,
  "status": "Completed"
}
```

#### DELETE /admin/orders.php
Delete an order.
**Query Params:**
- `id`: Order ID to delete

---

### Statistics

#### GET /admin/stats.php
Get dashboard statistics.
**Response includes:**
- Users count
- Products count (total, active)
- Accounts count (total, active, inactive)
- Orders count (total, by status)
- Revenue (total, pending)
- Recent orders
- Top selling products

---

## User/Public APIs

### Products

#### GET /user/allproducts.php
Get all active products (public view, no passwords).
**Query Params:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `search` (optional): Search in name/category

#### GET /user/product.php
Get single product by ID.
**Query Params:**
- `id`: Product ID

---

### Orders

#### POST /user/orders.php
Create a new order.
**Body:**
```json
{
  "product_id": 1,
  "quantity": 2,
  "customer_name": "John Doe",
  "customer_email": "john@example.com"
}
```

#### GET /user/orders.php
Get orders by customer email.
**Query Params:**
- `email`: Customer email address
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)

---

### Complaints Management

#### GET /admin/complaints.php
Get all complaints or single complaint.
**Query Params:**
- `id` (optional): Complaint ID for single complaint
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `search` (optional): Search in subject, message, customer_name, customer_email
- `status` (optional): Filter by status (Pending, In Progress, Resolved, Closed)
- `email` (optional): Filter by customer email

#### PUT /admin/complaints.php
Update complaint (status and admin response).
**Query Params:**
- `id`: Complaint ID (or include in body)
**Body:**
```json
{
  "id": 1,
  "status": "Resolved",
  "admin_response": "Issue resolved. Refund processed."
}
```

#### DELETE /admin/complaints.php
Delete a complaint.
**Query Params:**
- `id`: Complaint ID to delete

---

### Wallets Management

#### GET /admin/wallets.php
Get all wallets, single wallet by ID, or wallet by email.
**Query Params:**
- `id` (optional): Wallet ID for single wallet
- `email` (optional): Customer email to get wallet
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `search` (optional): Search in customer_email

#### POST /admin/wallets.php
Create new wallet or add/withdraw funds.
**Create Wallet:**
```json
{
  "customer_email": "user@example.com",
  "balance": 100.00
}
```

**Add/Withdraw Funds:**
```json
{
  "action": "add",
  "email": "user@example.com",
  "amount": 50.00,
  "description": "Refund for order #123"
}
```
Actions: `"add"` or `"withdraw"`

---

### Wallet Transactions

#### GET /admin/wallet_transactions.php
Get wallet transactions.
**Query Params:**
- `wallet_id` (optional): Wallet ID to filter transactions
- `email` (optional): Customer email to filter transactions
- `type` (optional): Filter by type (Credit, Debit)
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 50)

---

## User/Public APIs

### Products

#### GET /user/allproducts.php
Get all active products (public view, no passwords).
**Query Params:**
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)
- `search` (optional): Search in name/category

#### GET /user/product.php
Get single product by ID.
**Query Params:**
- `id`: Product ID

---

### Orders

#### POST /user/orders.php
Create a new order.
**Body:**
```json
{
  "product_id": 1,
  "quantity": 2,
  "customer_name": "John Doe",
  "customer_email": "john@example.com"
}
```

#### GET /user/orders.php
Get orders by customer email.
**Query Params:**
- `email`: Customer email address
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)

---

### Complaints

#### POST /user/complaints.php
Submit a new complaint.
**Body:**
```json
{
  "customer_email": "user@example.com",
  "customer_name": "John Doe",
  "order_id": 123,
  "subject": "Issue with product",
  "message": "Description of the issue..."
}
```
**Note:** `order_id` is optional.

#### GET /user/complaints.php
Get complaints by customer email.
**Query Params:**
- `email`: Customer email address
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10)

---

### Wallets

#### GET /user/wallets.php
Get wallet balance and recent transactions by email.
**Query Params:**
- `email`: Customer email address
**Response includes:**
- Wallet balance
- Recent 20 transactions
- Returns balance 0.00 if wallet doesn't exist

---

### Payments (Paystack)

#### POST /user/payments.php
Initialize Paystack payment for an order.
**Body:**
```json
{
  "order_id": 123,
  "customer_email": "user@example.com",
  "callback_url": "https://yoursite.com/payment/callback"
}
```
**Note:** `callback_url` is optional.

**Response:**
```json
{
  "success": true,
  "authorization_url": "https://checkout.paystack.com/...",
  "access_code": "access_code_here",
  "reference": "PAY_1234567890_123_5678",
  "order_id": 123,
  "amount": 29.99
}
```
**Usage:** Redirect user to `authorization_url` to complete payment.

#### GET /user/verify_payment.php
Verify Paystack payment status.
**Query Params:**
- `reference`: Payment reference returned from payment initialization

**Response:**
```json
{
  "success": true,
  "payment_status": "success",
  "order_id": 123,
  "reference": "PAY_1234567890_123_5678",
  "amount": 29.99,
  "paid_at": "2024-01-01T12:00:00.000Z"
}
```

**Payment Status Values:**
- `pending`: Payment not yet completed
- `success`: Payment completed successfully
- `failed`: Payment failed

---

## Response Format

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message"
}
```

### Error Response
```json
{
  "error": "Error message"
}
```

### Paginated Response
```json
{
  "success": true,
  "items": [ ... ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 100,
    "totalPages": 10
  }
}
```

---

## Status Codes

- `200` - Success
- `400` - Bad Request (missing/invalid parameters)
- `401` - Unauthorized (admin endpoints without auth)
- `404` - Not Found
- `405` - Method Not Allowed
- `409` - Conflict (e.g., duplicate email)
- `500` - Internal Server Error

