-- ISOLATED TEST DATABASE ONLY. Never import on production.
CREATE DATABASE veegame_admin_fixture CHARACTER SET utf8mb4;
USE veegame_admin_fixture;
CREATE TABLE shonu_subjects(id INT PRIMARY KEY, mobile VARCHAR(40),email VARCHAR(80),codechorkamukala VARCHAR(40),status INT,createdate DATETIME,shonullgnt DATETIME,akshinak TEXT,password VARCHAR(255),pwd TEXT) ENGINE=MyISAM;
CREATE TABLE shonu_kaichila(balakedara INT,motta VARCHAR(500),turnover VARCHAR(500),bonus VARCHAR(500)) ENGINE=MyISAM;
CREATE TABLE thevani(shonu INT PRIMARY KEY,balakedara INT,dharavahi VARCHAR(100),motta VARCHAR(500),sthiti VARCHAR(5),madari VARCHAR(10),mula VARCHAR(60),ullekha VARCHAR(100),dinankavannuracisi DATETIME) ENGINE=MyISAM;
CREATE TABLE hintegedukolli LIKE thevani;
ALTER TABLE hintegedukolli ADD remarks TEXT;
CREATE TABLE veegame_saas_settings(setting_key VARCHAR(80) PRIMARY KEY,setting_value TEXT,updated_at DATETIME) ENGINE=InnoDB;
INSERT INTO veegame_saas_settings VALUES('migration_state','preview',NOW());
INSERT INTO shonu_subjects VALUES(1,'9000000001','fixture@example.invalid','Fixture Owner',1,NOW(),NOW(),'FIXTURE-USER-TOKEN-DO-NOT-EXPOSE','FIXTURE-HASH-DO-NOT-EXPOSE','FIXTURE-PLAIN-DO-NOT-EXPOSE'),(2,'9000000002','fixture2@example.invalid','<script>alert(1)</script>',0,NOW(),NOW(),'','SECRET_HASH','SECRET_PLAIN');
INSERT INTO shonu_kaichila VALUES(1,'100.1200','100','0'),(2,'invalid-balance','0','0'),(3,NULL,'0','0'),(4,'0.123456789','0','0');
INSERT INTO thevani VALUES(1,1,'DEP-TEST-001','10.12345','0','1','Fixture only','TEST-REF',NOW());
INSERT INTO hintegedukolli VALUES(1,1,'WD-TEST-001','5.0000','0','1','Fixture only','TEST-REF',NOW(),'Fixture withdrawal');
