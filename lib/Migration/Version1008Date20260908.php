<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Product price history: store per-product price records (with an optional store
 * reference) synced from the Android app. Values are what the user recorded on a
 * given date, not derived from list items.
 *
 * @psalm-suppress UnusedClass
 */
class Version1008Date20260908 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbml_product_prices')) {
			$table = $schema->createTable('bbml_product_prices');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('product_id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('store_id', Types::STRING, ['length' => 36, 'notnull' => false]);
			$table->addColumn('value', Types::DECIMAL, ['precision' => 12, 'scale' => 2, 'notnull' => true]);
			$table->addColumn('price_date', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'bbml_product_prices_owner_idx');
			$table->addIndex(['product_id'], 'bbml_product_prices_product_idx');
			$table->addIndex(['store_id'], 'bbml_product_prices_store_idx');
		}

		return $schema;
	}
}
