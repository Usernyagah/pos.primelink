# How to build and run your local version of Dolibarr, quickly with Docker ?


## For a fast run of a demo of the local version, you can build a docker image pointing to your source repository

This process using the Dockerfile and docker-compose.yml file is a config file to use to build and run Dolibarr in the 
current workspace with Docker. This docker image is intended for **development usage** (See next chapter for a production usage).

Warning: There is no persistency of database. This process is for dev purpose only.


Build the containers:

	git clone https://github.com/Dolibarr/dolibarr.git dolibarr 
	
	cd dolibarr/dev/build/docker

	sudo docker-compose build [--no-cache]
	or
	sudo docker compose build [--no-cache]

	
Launch the containers:

	sudo -s
	export HOST_USER_ID=$(id -u)
	export HOST_GROUP_ID=$(id -g)
	export MYSQL_ROOT_PWD=$(tr -dc A-Za-z0-9 </dev/urandom | head -c 13; echo)
	
	docker-compose up -d
	or 
	docker compose up -d

Check that server process is on:
	
	docker compose ps

Check also logs of starting container:

	docker logs dolibarr-web-dev

The URL to go to the installed Dolibarr is :

    http://0.0.0.0:8080

To delete processes:

	docker compose kill stop


## Build and publish a self-contained image

From the root of your Dolibarr source checkout, build and publish the application image. The root-level `Dockerfile` is also detected automatically by deployment platforms that build from the repository root:

	docker build -t ghcr.io/usernyagah/pos.primelink:25.0.0-alpha .
	docker push ghcr.io/usernyagah/pos.primelink:25.0.0-alpha

Log in to GitHub Container Registry with a token that has `write:packages` permission before publishing. The image contains the Dolibarr application source; database configuration is completed through the installer at `/install/`. Mount persistent volumes at `/var/www/html/conf` and `/var/www/documents`, and provide a supported database separately. At startup, the container ensures the configuration volume and any existing `conf.php` are writable by Dolibarr's Apache user; do not make the configuration directory world-writable.

This fork brands the application as **PrimeLink ERPs** and uses the supplied Prime Link Technologies logo as the default login and public-page logo. Fresh installs also preselect the supermarket modules for checkout, stock, purchasing, barcode scanning, lot/expiry tracking, and margin reporting. Set the company's legal name and upload its business logo in the Dolibarr company setup after installation.

### Supermarket setup

The module preset is applied only when creating a new database. For an existing Dolibarr database, enable the required modules under **Home → Setup → Modules/Applications**:

- **TakePOS**, **Products**, **Categories**, **Bank/Cash**, and **Customer invoices** for checkout.
- **Stock**, **Barcode**, **Product lots/serial numbers**, **Supplier management**, **Supplier receptions**, **Customer orders**, and **Shipments** for store and back-office operations.
- **Proposals** and **Margins** for sales and profitability reporting.

Then configure the store before trading:

1. Create a warehouse for each store or stock location. Load product opening balances through the inventory workflow.
2. Create product categories, units, VAT rates, retail prices, and product barcodes. Test barcode scanning with the actual scanner in the POS terminal.
3. Enable batch/lot tracking only on perishable products that need traceability; capture sell-by/eat-by dates when receiving stock and rotate short-dated stock using the lot lists.
4. Create supplier records and use supplier orders and receptions to record deliveries before updating stock. Enter supplier invoices separately for accounts payable.
5. Configure a TakePOS terminal, receipt/printer and permitted payment methods. Assign cashiers the required POS rights and run a test sale, refund/credit, and end-of-day cash check before live use.
6. Review product stock alerts and sales/margin statistics routinely. Restrict price changes, discounts, stock corrections and refunds to authorized users.

Dolibarr does not implement supermarket-specific food-safety procedures automatically: configure lot traceability for applicable products and follow local rules for recalls, expiry handling, tax, receipts, and accounting.

## For a more robust or a production usage

If you want to execute an official Docker package, you can find it and read the doc on 

*https://hub.docker.com/r/dolibarr/dolibarr*
