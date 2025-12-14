## Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 18+
- npm

### Installation

1. **Configure environment files**
   
   Copy `.env.example` to `.env` in both `api` and `frontend` directories:
   ```bash
   cp api/.env.example api/.env
   cp frontend/.env.example frontend/.env
   ```

2. **Configure Pusher for real-time updates**
   
   Add your Pusher credentials to both `.env` files:
   
   **api/.env:**
   ```
   PUSHER_APP_ID=your_app_id
   PUSHER_APP_KEY=your_app_key
   PUSHER_APP_SECRET=your_app_secret
   PUSHER_APP_CLUSTER=your_cluster
   ```
   
   **frontend/.env:**
   ```
   VITE_PUSHER_APP_KEY=your_app_key
   VITE_PUSHER_APP_CLUSTER=your_cluster
   ```

3. **Run the setup script**
   ```bash
   ./setup.sh
   ```
   This will:
   - Install Composer dependencies
   - Generate Laravel application key
   - Create SQLite database
   - Run migrations and seed data
   - Install npm dependencies
   - Start the development servers

4. **Access the application**
   
   Open [http://localhost:5173](http://localhost:5173) in your browser.

## Default Data

The default seeder creates two test users:

| Email | Password |
|-------|----------|
| buyer@example.com | password |
| seller@example.com | password |


## Database design

```mermaid
erDiagram
    USERS ||--o{ ASSETS : have
    USERS ||--o{ ORDERS : place
    ORDERS ||--o{ TRADES : buy_order
    ORDERS ||--o{ TRADES : sell_order

    USERS {
        int id
        string name
        string email
        string password
        decimal balance
        timestamp email_verified_at
        string remember_token
        timestamp created_at
        timestamp updated_at
    }
    ASSETS {
        int id
        int user_id
        string symbol
        decimal amount
        decimal locked_amount
        timestamp created_at
        timestamp updated_at
    }
    ORDERS {
        int id
        int user_id
        string symbol
        enum side
        decimal price
        decimal amount
        enum status 
        decimal locked_funds
        timestamp created_at
        timestamp updated_at
    }
    TRADES {
        int id
        int buyer_id
        int seller_id
        int buy_order_id
        int sell_order_id
        string symbol
        decimal price
        decimal amount
        decimal total
        decimal fee
        timestamp created_at
        timestamp updated_at
    }
