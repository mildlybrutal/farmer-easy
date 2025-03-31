# Farmer Portal - Bidding System API Documentation

This document provides information about the bidding system API endpoints and how to test them.

## Overview

The bidding system allows retailers to create project requests and farmers to bid on these projects. The system includes:

1. Project management (retailers can create, update, and manage projects)
2. Bid submission and management (farmers can submit and update bids)
3. Messaging between farmers and retailers
4. Contract generation for accepted bids

## API Endpoints

### Projects API

**Base URL**: `/api/bidding/projects`

| Method | Endpoint | Description | Role Access |
|--------|----------|-------------|-------------|
| GET | `/api/bidding/projects` | Get all projects (filtered by role) | All authenticated users |
| GET | `/api/bidding/projects?id=<project_id>` | Get a specific project by ID | All authenticated users |
| POST | `/api/bidding/projects` | Create a new project | Retailers only |
| PUT | `/api/bidding/projects?id=<project_id>` | Update a project | Project owner (retailer) only |
| DELETE | `/api/bidding/projects?id=<project_id>` | Delete a project | Project owner (retailer) only |

### Bids API

**Base URL**: `/api/bidding/bids`

| Method | Endpoint | Description | Role Access |
|--------|----------|-------------|-------------|
| GET | `/api/bidding/bids` | Get all bids for the current user | All authenticated users |
| GET | `/api/bidding/bids?id=<bid_id>` | Get a specific bid by ID | Bid owner (farmer) or project owner (retailer) |
| GET | `/api/bidding/bids?project_id=<project_id>` | Get all bids for a project | Project owner (retailer) or bid owner (farmer) |
| POST | `/api/bidding/bids` | Create a new bid | Farmers only |
| PUT | `/api/bidding/bids?id=<bid_id>` | Update a bid | Bid owner (farmer) or project owner (retailer) |
| DELETE | `/api/bidding/bids?id=<bid_id>` | Delete a bid | Bid owner (farmer) only |

### Messages API

**Base URL**: `/api/bidding/messages`

| Method | Endpoint | Description | Role Access |
|--------|----------|-------------|-------------|
| GET | `/api/bidding/messages?bid_id=<bid_id>` | Get all messages for a bid | Bid owner (farmer) or project owner (retailer) |
| POST | `/api/bidding/messages` | Send a new message | Bid owner (farmer) or project owner (retailer) |

### Contracts API

**Base URL**: `/api/bidding/contracts`

| Method | Endpoint | Description | Role Access |
|--------|----------|-------------|-------------|
| GET | `/api/bidding/contracts?bid_id=<bid_id>` | Generate a contract for an accepted bid | Bid owner (farmer) or project owner (retailer) |

## Testing the API

You can test the API using tools like Postman, cURL, or any HTTP client. Here are some examples:

### Prerequisites

1. Make sure the server is running
2. You need to be authenticated (logged in) to access most endpoints

### Starting the PHP Server

```bash
cd /home/akash/Studies/farmer-portal/backend
php -S localhost:8000
```

### Testing with cURL

#### 1. Login (to get a session)

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "retailer@example.com", "password": "password123"}' \
  -c cookies.txt
```

#### 2. Create a Project (as a retailer)

```bash
curl -X POST http://localhost:8000/api/bidding/projects \
  -H "Content-Type: application/json" \
  -d '{"title": "Bulk Wheat Purchase", "description": "Looking for 500kg of wheat for our bakery", "deadline": "2025-04-30"}' \
  -b cookies.txt
```

#### 3. Get All Projects

```bash
curl -X GET http://localhost:8000/api/bidding/projects \
  -b cookies.txt
```

#### 4. Get a Specific Project

```bash
curl -X GET http://localhost:8000/api/bidding/projects?id=1 \
  -b cookies.txt
```

#### 5. Create a Bid (as a farmer)

First, login as a farmer:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "farmer@example.com", "password": "password123"}' \
  -c cookies.txt
```

Then create a bid:

```bash
curl -X POST http://localhost:8000/api/bidding/bids \
  -H "Content-Type: application/json" \
  -d '{"project_id": 1, "price": 15000, "terms": "Delivery within 2 weeks", "message": "I can provide high-quality wheat at a competitive price."}' \
  -b cookies.txt
```

#### 6. Get Bids for a Project

```bash
curl -X GET http://localhost:8000/api/bidding/bids?project_id=1 \
  -b cookies.txt
```

#### 7. Accept a Bid (as a retailer)

First, login as a retailer again:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "retailer@example.com", "password": "password123"}' \
  -c cookies.txt
```

Then accept the bid:

```bash
curl -X PUT http://localhost:8000/api/bidding/bids?id=1 \
  -H "Content-Type: application/json" \
  -d '{"status": "accepted", "message": "Your bid has been accepted. Let\'s finalize the details."}' \
  -b cookies.txt
```

#### 8. Send a Message

```bash
curl -X POST http://localhost:8000/api/bidding/messages \
  -H "Content-Type: application/json" \
  -d '{"bid_id": 1, "message": "When can we arrange for delivery?"}' \
  -b cookies.txt
```

#### 9. Get Messages for a Bid

```bash
curl -X GET http://localhost:8000/api/bidding/messages?bid_id=1 \
  -b cookies.txt
```

#### 10. Generate a Contract

```bash
curl -X GET http://localhost:8000/api/bidding/contracts?bid_id=1 \
  -b cookies.txt
```

## Testing with Postman

1. Import the following collection: [Farmer Portal API Collection](https://www.postman.com/collections/your-collection-id)
2. Set the base URL to `http://localhost:8000`
3. First, use the login request to authenticate
4. Then use the other requests to test the API endpoints

## Common HTTP Status Codes

- 200: Success
- 201: Created
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 422: Unprocessable Entity
- 500: Internal Server Error

## Notes

- The bidding system requires authentication for all endpoints
- Different roles (farmer, retailer) have different permissions
- Retailers can create projects and accept/reject bids
- Farmers can submit bids on open projects
- Both parties can communicate through the messaging system
- Once a bid is accepted, a contract can be generated
