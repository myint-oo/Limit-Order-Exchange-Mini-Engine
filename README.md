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
