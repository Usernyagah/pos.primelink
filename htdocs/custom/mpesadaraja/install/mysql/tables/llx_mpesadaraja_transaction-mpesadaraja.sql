-- M-Pesa Daraja STK Push transaction records.
CREATE TABLE llx_mpesadaraja_transaction (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL DEFAULT 1,
	fk_facture integer NOT NULL,
	fk_paiement integer,
	fk_user_author integer NOT NULL,
	reference varchar(64) NOT NULL,
	checkout_request_id varchar(100),
	merchant_request_id varchar(100),
	amount double(24,8) NOT NULL,
	phone varchar(16) NOT NULL,
	status varchar(16) NOT NULL,
	mpesa_receipt varchar(32),
	result_description varchar(255),
	datec datetime NOT NULL,
	tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uk_mpesadaraja_reference (reference),
	UNIQUE KEY uk_mpesadaraja_checkout (checkout_request_id),
	KEY idx_mpesadaraja_invoice (entity, fk_facture, status)
) ENGINE=innodb;
