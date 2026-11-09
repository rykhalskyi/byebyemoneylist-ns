<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Create the purchase-import inbox tables:
 * - bbml_purchase_imports: one uploaded purchase list
 * - bbml_purchase_import_items: raw items + mapping state
 * - bbml_purchase_import_categories: raw list categories + mapping state
 *
 * @psalm-suppress UnusedClass
 */
class Version1012Date20261109 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbml_purchase_imports')) {
			$table = $schema->createTable('bbml_purchase_imports');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('client_list_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('client_id', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('schema_version', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']);
			$table->addColumn('store_name', Types::STRING, ['length' => 255, 'notnull' => false]);
			$table->addColumn('store_address', Types::STRING, ['length' => 512, 'notnull' => false]);
			$table->addColumn('store_receipt_name', Types::STRING, ['length' => 255, 'notnull' => false]);
			$table->addColumn('currency', Types::STRING, ['length' => 8, 'notnull' => false]);
			$table->addColumn('final_total', Types::DECIMAL, ['precision' => 12, 'scale' => 2, 'notnull' => false]);
			$table->addColumn('list_created_at', Types::DATETIME, ['notnull' => false]);
			$table->addColumn('purchase_date', Types::DATETIME, ['notnull' => false]);
			$table->addColumn('raw_json', Types::TEXT, ['notnull' => true]);
			$table->addColumn('mapped_store_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('store_action', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']);
			$table->addColumn('suggested_store_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('suggested_store_name', Types::STRING, ['length' => 255, 'notnull' => false]);
			$table->addColumn('store_match_source', Types::STRING, ['length' => 16, 'notnull' => false]);
			$table->addColumn('store_confidence', Types::DECIMAL, ['precision' => 4, 'scale' => 3, 'notnull' => false]);
			$table->addColumn('approved_list_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('approved_at', Types::DATETIME, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['owner', 'client_list_id'], 'bbml_pi_owner_client_uniq');
			$table->addIndex(['owner', 'status'], 'bbml_pi_owner_status_idx');
		}

		if (!$schema->hasTable('bbml_purchase_import_items')) {
			$table = $schema->createTable('bbml_purchase_import_items');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('import_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('line_id', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('raw_name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('quantity', Types::DECIMAL, ['precision' => 12, 'scale' => 3, 'notnull' => true, 'default' => 1.0]);
			$table->addColumn('unit_price', Types::DECIMAL, ['precision' => 12, 'scale' => 2, 'notnull' => false]);
			$table->addColumn('discount', Types::DECIMAL, ['precision' => 12, 'scale' => 2, 'notnull' => false]);
			$table->addColumn('is_coupon', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('barcode', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('category_hint', Types::STRING, ['length' => 255, 'notnull' => false]);
			$table->addColumn('action', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']);
			$table->addColumn('mapped_product_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('mapped_category_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('new_product_name', Types::STRING, ['length' => 255, 'notnull' => false]);
			$table->addColumn('suggested_product_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('suggested_product_name', Types::STRING, ['length' => 255, 'notnull' => false]);
			$table->addColumn('suggested_category_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('match_source', Types::STRING, ['length' => 16, 'notnull' => false]);
			$table->addColumn('confidence', Types::DECIMAL, ['precision' => 4, 'scale' => 3, 'notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['import_id'], 'bbml_pii_import_idx');
		}

		if (!$schema->hasTable('bbml_purchase_import_categories')) {
			$table = $schema->createTable('bbml_purchase_import_categories');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('import_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('raw_name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('is_income', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('action', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']);
			$table->addColumn('mapped_category_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('suggested_category_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('match_source', Types::STRING, ['length' => 16, 'notnull' => false]);
			$table->addColumn('confidence', Types::DECIMAL, ['precision' => 4, 'scale' => 3, 'notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['import_id'], 'bbml_pic_import_idx');
		}

		return $schema;
	}
}
