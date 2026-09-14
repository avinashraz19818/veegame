-- LOCAL TEST DATABASE ONLY. Never run on a production database.
CREATE DATABASE IF NOT EXISTS veegame_fixture;
USE veegame_fixture;
CREATE TABLE IF NOT EXISTS shonu_subjects(id BIGINT PRIMARY KEY,mobile VARCHAR(40),status INT NOT NULL,akshinak TEXT,codechorkamukala VARCHAR(40)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS shonu_kaichila(balakedara BIGINT PRIMARY KEY,motta DECIMAL(18,4) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bajikattuttate(parichaya BIGINT PRIMARY KEY,byabaharkarta BIGINT,kalaparichaya VARCHAR(40),ojana INT,menge DECIMAL(18,4),wettanzahl INT,ketebida DECIMAL(18,4),phalaphala VARCHAR(20),sesabida DECIMAL(18,4),ergebnis INT NULL,tiarikala DATETIME) ENGINE=InnoDB;
