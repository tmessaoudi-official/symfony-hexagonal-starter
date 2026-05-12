Feature: User Management API
  As a client of the API
  I want to create and retrieve users
  So that I can manage the user base

  Background:
    Given I set header "Content-Type" with value "application/json"
    And I set header "Accept" with value "application/json"

  Scenario: Successfully create a new user
    When I send a "POST" request to "/api/users" with body:
      """
      {
        "email": "alice@example.com",
        "name": "Alice Smith"
      }
      """
    Then the response status code should be 201
    And the response should be in JSON
    And the JSON node "email" should be equal to "alice@example.com"
    And the JSON node "name" should be equal to "Alice Smith"
    And the JSON node "status" should be equal to "active"
    And the JSON node "id" should exist

  Scenario: Cannot create a user with an invalid email
    When I send a "POST" request to "/api/users" with body:
      """
      {
        "email": "not-an-email",
        "name": "Bob"
      }
      """
    Then the response status code should be 422
    And the response should be in JSON

  Scenario: Cannot create a user with a missing name
    When I send a "POST" request to "/api/users" with body:
      """
      {
        "email": "charlie@example.com",
        "name": ""
      }
      """
    Then the response status code should be 422

  Scenario: Cannot create a duplicate user
    Given there is a user with email "duplicate@example.com" and name "First User"
    When I send a "POST" request to "/api/users" with body:
      """
      {
        "email": "duplicate@example.com",
        "name": "Second User"
      }
      """
    Then the response status code should be 409

  Scenario: Retrieve a user by ID
    Given there is a user with email "findme@example.com" and name "Find Me"
    When I send a "GET" request to "/api/users/{findme@example.com::id}"
    Then the response status code should be 200
    And the JSON node "email" should be equal to "findme@example.com"

  Scenario: Returns 404 for unknown user ID
    When I send a "GET" request to "/api/users/00000000-0000-0000-0000-000000000000"
    Then the response status code should be 404

  Scenario: List users returns a paginated response
    Given there are 5 users in the system
    When I send a "GET" request to "/api/users?limit=3"
    Then the response status code should be 200
    And the JSON node "items" should have 3 elements
    And the JSON node "hasMore" should be equal to "true"
    And the JSON node "nextCursor" should exist

  Scenario: Create multiple users and list them all
    Given there is a user with email "list1@example.com" and name "List User One"
    And there is a user with email "list2@example.com" and name "List User Two"
    And there is a user with email "list3@example.com" and name "List User Three"
    When I send a "GET" request to "/api/users?limit=100"
    Then the response status code should be 200
    And the JSON node "items" should not be null

  Scenario: Get non-existent user returns 404
    When I send a "GET" request to "/api/users/00000000-0000-4000-8000-000000000000"
    Then the response status code should be 404

  Scenario: Create user with invalid email format returns 422 with error body
    When I send a "POST" request to "/api/users" with body:
      """
      {
        "email": "definitely@@not@@valid",
        "name": "Bad Format"
      }
      """
    Then the response status code should be 422
    And the response should be in JSON

  Scenario: Create user with name too short returns 422
    When I send a "POST" request to "/api/users" with body:
      """
      {
        "email": "shortname2@example.com",
        "name": "X"
      }
      """
    Then the response status code should be 422
