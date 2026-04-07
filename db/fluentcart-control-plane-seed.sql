-- EcomCine reusable FluentCart/control-plane seed
-- Generated from validated local baseline on 2026-03-27T17:43:45Z
-- Table prefix token: __WP_PREFIX__
-- Apply with: ./scripts/licensing/import-fluentcart-control-plane-seed.sh
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
START TRANSACTION;
DELETE FROM `__WP_PREFIX__fct_product_details`;
DELETE FROM `__WP_PREFIX__fct_product_variations`;
DELETE FROM `__WP_PREFIX__fct_product_meta`;
DELETE FROM `__WP_PREFIX__fct_customers`;
DELETE FROM `__WP_PREFIX__fct_customer_addresses`;
DELETE FROM `__WP_PREFIX__fct_orders`;
DELETE FROM `__WP_PREFIX__fct_order_addresses`;
DELETE FROM `__WP_PREFIX__fct_order_items`;
DELETE FROM `__WP_PREFIX__fct_order_meta`;
DELETE FROM `__WP_PREFIX__fct_order_operations`;
DELETE FROM `__WP_PREFIX__fct_order_transactions`;
DELETE FROM `__WP_PREFIX__fct_subscriptions`;
DELETE FROM `__WP_PREFIX__fct_meta`;
DELETE FROM `__WP_PREFIX__fct_licenses`;
DELETE FROM `__WP_PREFIX__fct_license_sites`;
DELETE FROM `__WP_PREFIX__fct_license_activations`;
DELETE FROM `__WP_PREFIX__fct_license_meta`;
DELETE FROM `__WP_PREFIX__fluentcart_licenses`;
DELETE FROM `__WP_PREFIX__options` WHERE option_name IN ('fluent_cart_store_settings','fluent_cart_modules_settings','fluent_cart_plugin_once_activated');
-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: wordpress
-- ------------------------------------------------------
-- Server version	8.0.45

/*!50503 SET NAMES utf8mb4 */;

--
-- Dumping data for table `__WP_PREFIX__fct_product_details`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_product_details` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_product_details` VALUES (2566,1096,'digital',0,0,NULL,NULL,0,'in-stock','simple',0,'{\"is_bundle_product\": null, \"sold_individually\": \"yes\"}','2026-03-09 18:19:11','2026-03-11 00:08:27'),(2569,1097,'digital',900,900,NULL,NULL,0,'in-stock','simple',0,'{\"is_bundle_product\": null}','2026-03-11 00:13:01','2026-03-11 00:14:29'),(2571,1098,'digital',1900,1900,NULL,NULL,0,'in-stock','simple',0,'{\"is_bundle_product\": null}','2026-03-11 00:16:15','2026-03-11 00:16:59'),(2573,1099,'digital',9900,9900,NULL,NULL,0,'in-stock','simple',0,'{\"is_bundle_product\": null}','2026-03-11 00:18:48','2026-03-11 00:19:34');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_product_details` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_product_variations`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_product_variations` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_product_variations` VALUES (1,1096,NULL,1,0,'Freemium',NULL,'freemium',0,'subscription','in-stock',0,1,0,0,1,'digital','active','false',0,0,900,NULL,'{\"description\":\"\",\"payment_type\":\"subscription\",\"times\":\"\",\"repeat_interval\":\"monthly\",\"trial_days\":\"\",\"billing_summary\":\"0 monthly Until Cancel\",\"manage_setup_fee\":\"no\",\"signup_fee_name\":\"\",\"signup_fee\":0,\"setup_fee_per_item\":\"no\",\"is_bundle_product\":null,\"installment\":\"no\"}','false','2026-03-09 18:19:11','2026-03-12 19:46:06'),(2,1097,NULL,1,0,'Solo',NULL,'subs-solo',0,'subscription','in-stock',0,1,0,0,1,'digital','active','false',900,0,1900,NULL,'{\"description\":\"\",\"payment_type\":\"subscription\",\"times\":\"\",\"repeat_interval\":\"monthly\",\"trial_days\":\"\",\"billing_summary\":\"9 monthly Until Cancel\",\"manage_setup_fee\":\"no\",\"signup_fee_name\":\"\",\"signup_fee\":0,\"setup_fee_per_item\":\"no\",\"is_bundle_product\":null}','false','2026-03-11 00:13:01','2026-03-11 00:40:58'),(3,1098,NULL,1,0,'Maestro',NULL,'subs-maestro',0,'subscription','in-stock',0,1,0,0,1,'digital','active','false',1900,0,4900,NULL,'{\"description\":\"\",\"payment_type\":\"subscription\",\"times\":\"\",\"repeat_interval\":\"monthly\",\"trial_days\":\"\",\"billing_summary\":\"9 monthly Until Cancel\",\"manage_setup_fee\":\"no\",\"signup_fee_name\":\"\",\"signup_fee\":0,\"setup_fee_per_item\":\"no\",\"is_bundle_product\":null,\"installment\":\"no\"}','false','2026-03-11 00:16:15','2026-03-11 00:57:06'),(4,1099,NULL,1,0,'Agency',NULL,'subs-agency',0,'subscription','in-stock',0,1,0,0,1,'digital','active','false',9900,0,24900,NULL,'{\"description\":\"\",\"payment_type\":\"subscription\",\"times\":\"\",\"repeat_interval\":\"monthly\",\"trial_days\":\"\",\"billing_summary\":\"9 monthly Until Cancel\",\"manage_setup_fee\":\"no\",\"signup_fee_name\":\"\",\"signup_fee\":0,\"setup_fee_per_item\":\"no\",\"is_bundle_product\":null,\"installment\":\"no\"}','false','2026-03-11 00:18:48','2026-03-12 19:49:31');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_product_variations` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_product_meta`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_product_meta` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_product_meta` VALUES (1,2566,NULL,'license_settings','{\"enabled\":\"no\",\"version\":\"0.1.6\",\"global_update_file\":{\"id\":\"\",\"driver\":\"local\",\"path\":\"\",\"url\":\"\"},\"variations\":{\"1\":{\"variation_id\":1,\"activation_limit\":\"1\",\"validity\":{\"unit\":\"year\",\"value\":1}}},\"wp\":{\"is_wp\":\"yes\",\"readme_url\":\"\",\"banner_url\":\"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp\",\"icon_url\":\"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp\",\"required_php\":\"\",\"required_wp\":\"\"},\"prefix\":\"WMOS\"}','2026-03-11 00:11:05','2026-03-11 00:11:32'),(2,2569,NULL,'license_settings','{\"enabled\":\"yes\",\"version\":\"0.1.6\",\"global_update_file\":{\"id\":\"\",\"driver\":\"local\",\"path\":\"\",\"url\":\"\"},\"variations\":{\"2\":{\"variation_id\":2,\"activation_limit\":\"3\",\"validity\":{\"unit\":\"month\",\"value\":1}}},\"wp\":{\"is_wp\":\"yes\",\"readme_url\":\"https://webmasteros.com/changelog\",\"banner_url\":\"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp\",\"icon_url\":\"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp\",\"required_php\":\"\",\"required_wp\":\"\"},\"prefix\":\"wmos\"}','2026-03-11 00:15:31','2026-03-11 00:18:11'),(3,2571,NULL,'license_settings','{\"enabled\":\"yes\",\"version\":\"0.1.6\",\"global_update_file\":{\"id\":\"\",\"driver\":\"local\",\"path\":\"\",\"url\":\"\"},\"variations\":{\"3\":{\"variation_id\":3,\"activation_limit\":\"10\",\"validity\":{\"unit\":\"month\",\"value\":1}}},\"wp\":{\"is_wp\":\"yes\",\"readme_url\":\"https://webmasteros.com/changelog\",\"banner_url\":\"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp\",\"icon_url\":\"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp\",\"required_php\":\"\",\"required_wp\":\"\"},\"prefix\":\"wmos\"}','2026-03-11 00:16:15','2026-03-11 00:17:49'),(4,2573,NULL,'license_settings','{\"enabled\":\"yes\",\"version\":\"0.1.6\",\"global_update_file\":{\"id\":\"\",\"driver\":\"local\",\"path\":\"\",\"url\":\"\"},\"variations\":{\"4\":{\"variation_id\":4,\"activation_limit\":\"100\",\"validity\":{\"unit\":\"month\",\"value\":1}}},\"wp\":{\"is_wp\":\"yes\",\"readme_url\":\"https://webmasteros.com/changelog\",\"banner_url\":\"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp\",\"icon_url\":\"https://webmasteros.com/wp-content/uploads/2026/03/WMOS-Logo-150x150.webp\",\"required_php\":\"\",\"required_wp\":\"\"},\"prefix\":\"wmos\"}','2026-03-11 00:18:48','2026-03-11 00:19:43'),(5,2571,NULL,'wmos_allowances_v1','{\"defaults\":{\"ai_queries_hour\":30,\"ai_queries_day\":360,\"ai_queries_month\":7200,\"remixes_max\":150,\"promotions_max\":75,\"queue_max\":100,\"ai_mode\":\"mutualized_ai\"},\"variations\":[]}',NULL,NULL),(6,2569,NULL,'wmos_allowances_v1','{\"defaults\":{\"ai_queries_hour\":12,\"ai_queries_day\":120,\"ai_queries_month\":2500,\"remixes_max\":10,\"promotions_max\":10,\"queue_max\":50,\"ai_mode\":\"mutualized_ai\"},\"variations\":[]}',NULL,NULL),(7,2566,NULL,'wmos_allowances_v1','{\"defaults\":{\"ai_queries_hour\":1,\"ai_queries_day\":1,\"ai_queries_month\":30,\"remixes_max\":1,\"promotions_max\":1,\"queue_max\":10,\"ai_mode\":\"mutualized_ai\"},\"variations\":[]}',NULL,NULL),(8,2573,NULL,'wmos_allowances_v1','{\"defaults\":{\"ai_queries_hour\":100,\"ai_queries_day\":1000,\"ai_queries_month\":30000,\"remixes_max\":10000,\"promotions_max\":10000,\"queue_max\":1000,\"ai_mode\":\"confidential_ai\"},\"variations\":[]}',NULL,NULL);
/*!40000 ALTER TABLE `__WP_PREFIX__fct_product_meta` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_customers`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_customers` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_customers` VALUES (1,1,0,'esfihm@gmail.com','teramohadmin','','active',NULL,3,12700,'2026-03-11 00:52:53','2026-03-11 00:59:21',4233.33,'','cf8b841a79a208c3c14678fcb1ea4f5b','MU','deffeef','','41845845','2026-03-11 00:52:53','2026-03-11 00:59:39');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_customers` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_customer_addresses`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_customer_addresses` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_customer_addresses` VALUES (1,1,1,'billing','active','','teramohadmin ','wdwdwd','','deffeef','',NULL,'esfihm@gmail.com','41845845','MU','{\"other_data\": {\"last_name\": \"\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:52:53','2026-03-11 00:52:53'),(2,1,1,'shipping','active','','teramohadmin ','wdwdwd','','deffeef','',NULL,'esfihm@gmail.com','41845845','MU','{\"other_data\": {\"last_name\": \"\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:52:53','2026-03-11 00:52:53');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_customer_addresses` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_orders`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_orders` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_orders` VALUES (1,'completed',NULL,1,'INV-1','digital','subscription','test','',1,'offline_payment','paid','Cash','USD',900,0,0,0,0,0,0,900,900,0,1.0000,0,'','197.225.123.51','2026-03-11 00:53:46',NULL,'1cef3fbd62314bb74b0694577874e0f4','{\"user_tz\": \"Indian/Mauritius\", \"create_account_after_paid\": \"yes\"}','2026-03-11 00:52:53','2026-03-11 00:53:46'),(2,'on-hold',NULL,NULL,'','digital','subscription','test','',1,'offline_payment','pending','Cash','USD',1900,0,0,0,0,0,0,1900,0,0,1.0000,0,'','197.225.123.51',NULL,NULL,'b2f493200a65a6517064fc43b4f256d7','{\"user_tz\": \"Indian/Mauritius\", \"create_account_after_paid\": \"yes\"}','2026-03-11 00:57:33','2026-03-11 00:57:33'),(3,'completed',NULL,2,'INV-2','digital','subscription','test','',1,'offline_payment','paid','Cash','USD',1900,0,0,0,0,0,0,1900,1900,0,1.0000,0,'','197.225.123.51','2026-03-11 00:58:42',NULL,'c71565883c5b15b3093639fe34a0ca6e','{\"user_tz\": \"Indian/Mauritius\", \"create_account_after_paid\": \"yes\"}','2026-03-11 00:58:15','2026-03-11 00:58:42'),(4,'completed',NULL,3,'INV-3','digital','subscription','test','',1,'offline_payment','paid','Cash','USD',9900,0,0,0,0,0,0,9900,9900,0,1.0000,0,'','197.225.123.51','2026-03-11 00:59:40',NULL,'7fe99cf633471b3f9be3a3aeceb0e7f8','{\"user_tz\": \"Indian/Mauritius\", \"create_account_after_paid\": \"yes\"}','2026-03-11 00:59:21','2026-03-11 00:59:40');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_orders` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_order_addresses`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_addresses` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_order_addresses` VALUES (1,1,'billing','teramohadmin','wdwdwd','','deffeef','','41845845','MU','{\"other_data\": {\"email\": \"esfihm@gmail.com\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:52:53','2026-03-11 00:52:53'),(2,1,'shipping','teramohadmin','wdwdwd','','deffeef','','41845845','MU','{\"other_data\": {\"email\": \"esfihm@gmail.com\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:52:53','2026-03-11 00:52:53'),(3,2,'billing','teramohadmin','wdwdwd','','deffeef','','41845845','MU','{\"other_data\": {\"email\": \"esfihm@gmail.com\", \"address_id\": \"1\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:57:33','2026-03-11 00:57:33'),(4,2,'shipping','teramohadmin','wdwdwd','','deffeef','','41845845','MU','{\"other_data\": {\"email\": \"esfihm@gmail.com\", \"address_id\": \"1\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:57:33','2026-03-11 00:57:33'),(5,3,'billing','teramohadmin','wdwdwd','','deffeef','','41845845','MU','{\"other_data\": {\"email\": \"esfihm@gmail.com\", \"address_id\": \"1\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:58:15','2026-03-11 00:58:15'),(6,3,'shipping','teramohadmin','wdwdwd','','deffeef','','41845845','MU','{\"other_data\": {\"email\": \"esfihm@gmail.com\", \"address_id\": \"1\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:58:15','2026-03-11 00:58:15'),(7,4,'billing','teramohadmin','wdwdwd','','deffeef','','41845845','MU','{\"other_data\": {\"email\": \"esfihm@gmail.com\", \"address_id\": \"1\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:59:21','2026-03-11 00:59:21'),(8,4,'shipping','teramohadmin','wdwdwd','','deffeef','','41845845','MU','{\"other_data\": {\"email\": \"esfihm@gmail.com\", \"address_id\": \"1\", \"first_name\": \"teramohadmin\"}}','2026-03-11 00:59:21','2026-03-11 00:59:21');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_addresses` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_order_items`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_items` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_order_items` VALUES (1,1,1097,'digital','subscription','Solo','Solo',2,0,1,900,0,900,0,0,0,900,0,1,'{\"times\": \"\", \"signup_fee\": 0, \"trial_days\": \"\", \"description\": \"\", \"installment\": \"no\", \"payment_type\": \"subscription\", \"billing_summary\": \"9 monthly Until Cancel\", \"repeat_interval\": \"monthly\", \"signup_fee_name\": \"\", \"manage_setup_fee\": \"no\", \"is_bundle_product\": null, \"setup_fee_per_item\": \"no\"}','[]',0,NULL,'2026-03-11 00:52:53','2026-03-11 00:52:53'),(2,2,1098,'digital','subscription','Maestro','Maestro',3,0,1,1900,0,1900,0,0,0,1900,0,1,'{\"times\": \"\", \"signup_fee\": 0, \"trial_days\": \"\", \"description\": \"\", \"installment\": \"no\", \"payment_type\": \"subscription\", \"billing_summary\": \"9 monthly Until Cancel\", \"repeat_interval\": \"monthly\", \"signup_fee_name\": \"\", \"manage_setup_fee\": \"no\", \"is_bundle_product\": null, \"setup_fee_per_item\": \"no\"}','[]',0,NULL,'2026-03-11 00:57:33','2026-03-11 00:57:33'),(3,3,1098,'digital','subscription','Maestro','Maestro',3,0,1,1900,0,1900,0,0,0,1900,0,1,'{\"times\": \"\", \"signup_fee\": 0, \"trial_days\": \"\", \"description\": \"\", \"installment\": \"no\", \"payment_type\": \"subscription\", \"billing_summary\": \"9 monthly Until Cancel\", \"repeat_interval\": \"monthly\", \"signup_fee_name\": \"\", \"manage_setup_fee\": \"no\", \"is_bundle_product\": null, \"setup_fee_per_item\": \"no\"}','[]',0,NULL,'2026-03-11 00:58:15','2026-03-11 00:58:15'),(4,4,1099,'digital','subscription','Agency','Agency',4,0,1,9900,0,9900,0,0,0,9900,0,1,'{\"times\": \"\", \"signup_fee\": 0, \"trial_days\": \"\", \"description\": \"\", \"installment\": \"no\", \"payment_type\": \"subscription\", \"billing_summary\": \"9 monthly Until Cancel\", \"repeat_interval\": \"monthly\", \"signup_fee_name\": \"\", \"manage_setup_fee\": \"no\", \"is_bundle_product\": null, \"setup_fee_per_item\": \"no\"}','[]',0,NULL,'2026-03-11 00:59:21','2026-03-11 00:59:21');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_items` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_order_meta`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_meta` DISABLE KEYS */;
/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_meta` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_order_operations`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_operations` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_order_operations` VALUES (1,1,NULL,0,0,'','','','','','','','',NULL,'2026-03-11 00:52:53','2026-03-11 00:52:53'),(2,2,NULL,0,0,'','','','','','','','',NULL,'2026-03-11 00:57:33','2026-03-11 00:57:33'),(3,3,NULL,0,0,'','','','','','','','',NULL,'2026-03-11 00:58:15','2026-03-11 00:58:15'),(4,4,NULL,0,0,'','','','','','','','',NULL,'2026-03-11 00:59:21','2026-03-11 00:59:21');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_operations` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_order_transactions`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_transactions` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_order_transactions` VALUES (1,1,'subscription','subscription',1,NULL,NULL,'','offline_payment','test','offline_payment','succeeded','USD',900,1,'f6a1225d6be50a1ac3238d8883d7ab31','[]','2026-03-11 00:52:53','2026-03-11 00:53:46'),(2,2,'subscription','charge',2,NULL,NULL,'','offline_payment','test','offline_payment','pending','USD',1900,1,'d0b7452ca65a0530564b66665757846c','[]','2026-03-11 00:57:33','2026-03-11 00:57:33'),(3,3,'subscription','subscription',3,NULL,NULL,'','offline_payment','test','offline_payment','succeeded','USD',1900,1,'ac2b2d5be6b01241c89abbd8ff6b6761','[]','2026-03-11 00:58:15','2026-03-11 00:58:42'),(4,4,'subscription','subscription',4,NULL,NULL,'','offline_payment','test','offline_payment','succeeded','USD',9900,1,'060a6d4d97808dc496aaaef4482da366','[]','2026-03-11 00:59:21','2026-03-11 00:59:39');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_order_transactions` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_subscriptions`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_subscriptions` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_subscriptions` VALUES (1,'629fcbb9ad9ce49d9cc25083bd8f7072',1,1,2569,'Solo - Solo',1,2,'monthly',0,0,900,0,900,0,0,NULL,NULL,NULL,NULL,'automatic',NULL,0,NULL,NULL,NULL,'pending',NULL,NULL,'offline_payment','{\"currency\": \"USD\", \"is_trial_days_simulated\": \"no\"}','2026-03-11 00:52:53','2026-03-11 00:52:53'),(2,'2f69f68518894e12551d086086de303b',1,2,2571,'Maestro - Maestro',1,3,'monthly',0,0,1900,0,1900,0,0,NULL,NULL,NULL,NULL,'automatic',NULL,0,NULL,NULL,NULL,'pending',NULL,NULL,'offline_payment','{\"currency\": \"USD\", \"is_trial_days_simulated\": \"no\"}','2026-03-11 00:57:33','2026-03-11 00:57:33'),(3,'b541dac4353b54ef23dced2e85825b95',1,3,2571,'Maestro - Maestro',1,3,'monthly',0,0,1900,0,1900,0,0,NULL,NULL,NULL,NULL,'automatic',NULL,0,NULL,NULL,NULL,'pending',NULL,NULL,'offline_payment','{\"currency\": \"USD\", \"is_trial_days_simulated\": \"no\"}','2026-03-11 00:58:15','2026-03-11 00:58:15'),(4,'ffe672b9142edccf5a6ecdd6b187b86d',1,4,2573,'Agency - Agency',1,4,'monthly',0,0,9900,0,9900,0,0,NULL,NULL,NULL,NULL,'automatic',NULL,0,NULL,NULL,NULL,'pending',NULL,NULL,'offline_payment','{\"currency\": \"USD\", \"is_trial_days_simulated\": \"no\"}','2026-03-11 00:59:21','2026-03-11 00:59:21');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_subscriptions` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_meta`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_meta` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_meta` VALUES (1,'option',NULL,'fluent_cart_payment_settings_stripe','{\"is_active\":\"yes\",\"define_test_keys\":false,\"define_live_keys\":false,\"test_publishable_key\":\"\",\"test_secret_key\":\"\",\"test_webhook_secret\":\"\",\"live_publishable_key\":\"pk_live_51FB4mCJV4TDq0RwSSvqweJ3weu3jW4PK8gnFpiE1UzQwm1JVt2iAT2iOu1sh9Mq8PSRYB7vDpUGAFsAROb6FdN9I00tA20tpbo\",\"live_secret_key\":\"1Lu9O97LF793EUCn0zzzZ0NvQlZlOFZ2MHg2ZXViSjl4eWxPUFJGTGJDRVllUmx3M1JoTHZpWWpJYUxCOTRxSjZtR3VEZGk3Z1BVZUV5elhYR3cyWDgxbEZZRkhYT1Nxd3ROTU1UY0hXMlF5RXVrbGNObXRGWDVBNW9vTmYrVUM3SHIwZ3VOL2hUY2J0cTVFZHUvb2E0SndhTE5rZnc3TU83MEM4bE96TXdGL05zZGJTcnJhSkV6NEZPSWdHelUwc0pRS2YxZm5vaUpjM2REeE5RSmN3OWZzaVZnWklpdGU4WDdSMzJNM3E3V3dWa0Q0QlRkSQ==\",\"live_webhook_secret\":\"\",\"payment_mode\":\"live\",\"checkout_mode\":\"onsite\",\"live_is_encrypted\":\"yes\",\"test_is_encrypted\":\"no\",\"secure\":\"yes\",\"live_connect_hash\":\"3a1623885fe019553bbd8c681fa58072\",\"live_account_id\":\"acct_1FB4mCJV4TDq0RwS\"}','2026-03-11 00:41:53','2026-03-11 00:51:26'),(2,'option',NULL,'fluent_cart_payment_settings_offline_payment','{\"is_active\":\"yes\",\"payment_mode\":\"live\",\"checkout_label\":\"Cash\"}','2026-03-11 00:52:30','2026-03-11 00:52:38');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_meta` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_licenses`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_licenses` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_licenses` VALUES (1,'active',3,0,'wmosa68e07dbbeca57077a7285ad0e8f4d96',2569,2,1,NULL,1,'2026-04-11 00:53:46',NULL,NULL,1,'[]','2026-03-11 00:53:46','2026-03-11 18:16:25'),(2,'inactive',10,0,'wmos773a1c71af46ea15e0a405eb2a358cb4',2571,3,3,NULL,1,'2026-04-11 00:58:42',NULL,NULL,3,'[]','2026-03-11 00:58:42','2026-03-11 00:58:42'),(3,'inactive',100,0,'wmos408dc5d94f8685e068bf1983722c2099',2573,4,4,NULL,1,'2026-04-11 00:59:39',NULL,NULL,4,'[]','2026-03-11 00:59:39','2026-03-11 00:59:39');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_licenses` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_license_sites`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_license_sites` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fct_license_sites` VALUES (1,'wmos-license-probe.example','8.2.0','6.8.1',NULL,'2026-03-11 01:13:01','2026-03-11 01:13:01');
/*!40000 ALTER TABLE `__WP_PREFIX__fct_license_sites` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_license_activations`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_license_activations` DISABLE KEYS */;
/*!40000 ALTER TABLE `__WP_PREFIX__fct_license_activations` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fct_license_meta`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fct_license_meta` DISABLE KEYS */;
/*!40000 ALTER TABLE `__WP_PREFIX__fct_license_meta` ENABLE KEYS */;

--
-- Dumping data for table `__WP_PREFIX__fluentcart_licenses`
--

/*!40000 ALTER TABLE `__WP_PREFIX__fluentcart_licenses` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__fluentcart_licenses` VALUES (15,'wmosa68e07dbbeca57077a7285ad0e8f4d96',2569,2,3,'active','2026-03-27 17:43:14','2026-03-27 17:43:14'),(16,'wmos773a1c71af46ea15e0a405eb2a358cb4',2571,3,10,'inactive','2026-03-27 17:43:14','2026-03-27 17:43:14'),(17,'wmos408dc5d94f8685e068bf1983722c2099',2573,4,100,'inactive','2026-03-27 17:43:14','2026-03-27 17:43:14');
/*!40000 ALTER TABLE `__WP_PREFIX__fluentcart_licenses` ENABLE KEYS */;


-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: wordpress
-- ------------------------------------------------------
-- Server version	8.0.45

/*!50503 SET NAMES utf8mb4 */;

--
-- Dumping data for table `__WP_PREFIX__options`
--
-- WHERE:  option_name IN ('fluent_cart_store_settings','fluent_cart_modules_settings','fluent_cart_plugin_once_activated')

/*!40000 ALTER TABLE `__WP_PREFIX__options` DISABLE KEYS */;
INSERT INTO `__WP_PREFIX__options` VALUES (80624,'fluent_cart_modules_settings','a:4:{s:9:\"turnstile\";a:3:{s:6:\"active\";s:2:\"no\";s:8:\"site_key\";s:0:\"\";s:10:\"secret_key\";s:0:\"\";}s:16:\"stock_management\";a:1:{s:6:\"active\";s:2:\"no\";}s:7:\"license\";a:1:{s:6:\"active\";s:3:\"yes\";}s:10:\"order_bump\";a:1:{s:6:\"active\";s:2:\"no\";}}','on'),(80625,'fluent_cart_plugin_once_activated','1','on'),(80623,'fluent_cart_store_settings','a:44:{s:10:\"store_name\";s:11:\"WebMasterOS\";s:30:\"note_for_user_account_creation\";s:31:\"An user account will be created\";s:20:\"checkout_button_text\";s:8:\"Checkout\";s:21:\"view_cart_button_text\";s:9:\"View Cart\";s:16:\"cart_button_text\";s:11:\"Add To Cart\";s:17:\"popup_button_text\";s:12:\"View Product\";s:24:\"out_of_stock_button_text\";s:13:\"Not Available\";s:17:\"currency_position\";s:6:\"before\";s:17:\"decimal_separator\";s:3:\"dot\";s:21:\"checkout_method_style\";s:4:\"logo\";s:21:\"enable_modal_checkout\";s:2:\"no\";s:17:\"require_logged_in\";s:2:\"no\";s:21:\"show_cart_icon_in_nav\";s:2:\"no\";s:22:\"show_cart_icon_in_body\";s:3:\"yes\";s:24:\"additional_address_field\";s:3:\"yes\";s:17:\"hide_coupon_field\";s:2:\"no\";s:26:\"user_account_creation_mode\";s:3:\"all\";s:16:\"checkout_page_id\";s:4:\"2560\";s:22:\"custom_payment_page_id\";s:0:\"\";s:20:\"registration_page_id\";s:0:\"\";s:13:\"login_page_id\";s:0:\"\";s:12:\"cart_page_id\";s:4:\"2561\";s:15:\"receipt_page_id\";s:4:\"2562\";s:12:\"shop_page_id\";s:4:\"2563\";s:24:\"customer_profile_page_id\";s:4:\"2564\";s:26:\"customer_profile_page_slug\";s:16:\"customer-profile\";s:8:\"currency\";s:3:\"USD\";s:14:\"store_address1\";s:0:\"\";s:14:\"store_address2\";s:0:\"\";s:10:\"store_city\";s:0:\"\";s:13:\"store_country\";s:2:\"US\";s:14:\"store_postcode\";s:0:\"\";s:11:\"store_state\";s:0:\"\";s:36:\"show_relevant_product_in_single_page\";s:3:\"yes\";s:30:\"show_relevant_product_in_modal\";s:0:\"\";s:10:\"order_mode\";s:4:\"live\";s:14:\"variation_view\";s:4:\"both\";s:17:\"variation_columns\";s:7:\"masonry\";s:36:\"enable_early_payment_for_installment\";s:3:\"yes\";s:16:\"modules_settings\";a:0:{}s:18:\"min_receipt_number\";s:1:\"1\";s:10:\"inv_prefix\";s:4:\"INV-\";s:10:\"store_logo\";a:3:{s:2:\"id\";s:4:\"2312\";s:3:\"url\";s:71:\"https://webmasteros.com/wp-content/uploads/2026/03/WM-Febicon_WebP.webp\";s:5:\"title\";s:15:\"WM Febicon_WebP\";}s:15:\"query_timestamp\";i:1773044159289;}','on');
/*!40000 ALTER TABLE `__WP_PREFIX__options` ENABLE KEYS */;


COMMIT;
