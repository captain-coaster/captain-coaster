# Captain Coaster

## About us

Captain Coaster is the ultimate guide for roller coaster enthusiasts!
Rate, write reviews, and craft top lists for the coasters you've ridden.
Join us in shaping the world's best roller coaster rankings!

## Installation

### Option 1: Local Development with Symfony CLI

1. Clone the project
2. Install [Symfony CLI](https://symfony.com/download)
3. Install PHP 8.3 locally
4. Install Composer dependencies
    ```shell
    composer install
    ```
5. Start the database services using Docker
    ```shell
    docker-compose up -d
    ```
    This will start:
    - MariaDB 10.11
    - Redis
    - Adminer on localhost:8081
6. Create a `captain` database on adminer, and import a dump file
7. Start the Symfony development server
    ```shell
    symfony server:start
    ```
8. Browse the application at the URL provided by Symfony CLI (typically http://localhost:8000)

### Option 2: Full Docker Setup

1. Clone the project
2. Build and start all containers using the full Docker Compose configuration
    ```shell
    docker compose -f docker-compose.full.yml up --build -d
    ```
    Containers provided:
    - nginx on localhost:8080
    - PHP 8.3
    - MariaDB 10.11
    - Redis
    - Adminer on localhost:8081
3. Install composer dependencies
    ```shell
    docker exec -ti php-captain composer install
    ```
4. Create a `captain` database on adminer, and import a dump file
5. Browse `localhost:8080`

## Docker Compose Structure

The project uses a modular Docker Compose setup:

-   `docker-compose.yml` - Base configuration with database services (MariaDB, Redis, Adminer)
-   `docker-compose.full.yml` - Imports the base configuration and adds web services (nginx, PHP, Node)

## API Documentation

The API documentation is available at `/api/docs` when the application is running.

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin feature/my-new-feature`
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
