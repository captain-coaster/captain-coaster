Captain Coaster
======

## About us

Captain Coaster is the ultimate guide for roller coaster enthusiasts!
Rate, write reviews, and craft top lists for the coasters you've ridden.
Join us in shaping the world's best roller coaster rankings!

## Installation

1. Clone the project
2. Build and start all containers using docker-compose

    ```shell
    docker-compose up --build -d
    ```
   Containers provided:
    * nginx on localhost:8080
    * PHP 8.3
    * Node 16 for Webpack Encore
    * MariaDB 10.11
    * Adminer on localhost:8081

3. Install composer dependencies
    ```shell
    docker exec -ti php-captain composer install
    ```
4. Create a `captain` database on adminer, and import a dump file
5. Browse `localhost:8080`
