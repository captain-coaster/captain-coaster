# Captain Coaster

## About us

Captain Coaster is the ultimate guide for roller coaster enthusiasts!
Rate, write reviews, and craft top lists for the coasters you've ridden.
Join us in shaping the world's best roller coaster rankings!

## Installation

### Option 1: Local Development with Symfony CLI

1. Clone the project
2. Install [Symfony CLI](https://symfony.com/download)
3. Install PHP 8.5 locally
4. Install [Composer](https://getcomposer.org/download/) locally
5. Install Composer dependencies
    ```shell
    composer install
    ```
6. Start the database services using Docker
    ```shell
    docker-compose up -d
    ```
    This will start:
    - MariaDB 11.8
    - Redis
    - Adminer on localhost:8081
7. Create a `.env.local` file with a working database connection
    ```
    DATABASE_URL=mysql://root:root123@127.0.0.1:3306/captain?serverVersion=mariadb-11.8.0&charset=utf8mb4
    ```
8. Set up the database schema and load sample data
    ```shell
    composer db-setup
    ```
    This creates the database, builds the schema from the current entity
    mappings, marks existing migrations as applied, and loads a small set of
    sample parks/coasters/users so the app has something to show. If you
    have access to a production database dump, you can import that instead —
    it already contains a full schema and doesn't need this step, though you
    should still run `bin/console doctrine:migrations:sync-metadata-storage`
    and `bin/console doctrine:migrations:version --add --all` afterward if
    its migration history isn't already up to date.
9. Start the Symfony development server
    ```shell
    symfony server:start
    ```
10. Browse the application at the URL provided by Symfony CLI (typically http://localhost:8000)

### Option 2: Full Docker Setup

1. Clone the project
2. Build and start all containers using the full Docker Compose configuration
    ```shell
    docker compose -f docker-compose.full.yml up --build -d
    ```
    Containers provided:
    - nginx on localhost:8080
    - PHP 8.5
    - MariaDB 11.8
    - Redis
    - Adminer on localhost:8081
3. Install composer dependencies
    ```shell
    docker exec -ti php-captain composer install
    ```
4. Create a `.env.local` file with a working database connection
    ```
    DATABASE_URL=mysql://root:root123@db:3306/captain?serverVersion=mariadb-11.8.0&charset=utf8mb4
    ```
5. Set up the database schema and load sample data
    ```shell
    docker exec -ti php-captain composer db-setup
    ```
    Same as above: builds the schema, marks existing migrations as applied,
    and loads sample data. A production dump can be imported instead if you
    have access to one.
6. Browse `localhost:8080`

## Docker Compose Structure

The project uses a modular Docker Compose setup:

-   `docker-compose.yml` - Base configuration with database services (MariaDB, Redis, Adminer)
-   `docker-compose.full.yml` - Imports the base configuration and adds web services (nginx, PHP, Node)

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin feature/my-new-feature`
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
