<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * LLM Profiles: store configured LLM providers, model settings, and encrypted API keys
 * for OCR scanning and AI integrations.
 *
 * @psalm-suppress UnusedClass
 */
class Version1009Date20260914 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('bbml_llm_profiles')) {
			$table = $schema->createTable('bbml_llm_profiles');
			$table->addColumn('id', Types::STRING, ['length' => 36, 'notnull' => true]);
			$table->addColumn('owner', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 128, 'notnull' => true]);
			$table->addColumn('provider', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('api_key', Types::TEXT, ['notnull' => true]);
			$table->addColumn('model', Types::STRING, ['length' => 128, 'notnull' => false]);
			$table->addColumn('connect_timeout', Types::INTEGER, ['notnull' => true, 'default' => 30]);
			$table->addColumn('read_timeout', Types::INTEGER, ['notnull' => true, 'default' => 60]);
			$table->addColumn('max_tokens', Types::INTEGER, ['notnull' => true, 'default' => 2048]);
			$table->addColumn('is_active', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['owner'], 'bbml_llm_profiles_owner_idx');
		}

		return $schema;
	}
}
