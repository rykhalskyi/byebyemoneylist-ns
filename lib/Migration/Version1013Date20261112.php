<?php

declare(strict_types=1);

namespace OCA\ByeByeMoneyList\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\ICompositeExpression;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * To Buy list redesign data model:
 * - bbml_lists.kind (NEED_TO_BUY | PURCHASE | INCOME | SUBSCRIPTION)
 * - bbml_lists.is_active (at most one active To Buy list per owner)
 * - bbml_list_items.product_id becomes nullable (plain-text To Buy items)
 *
 * Legacy rows are backfilled from the old flags and open-list items are converted to
 * plain text, mirroring the Android redesign.
 *
 * @psalm-suppress UnusedClass
 */
class Version1013Date20261112 extends SimpleMigrationStep {
	private IDBConnection $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		$lists = $schema->getTable('bbml_lists');
		if (!$lists->hasColumn('kind')) {
			$lists->addColumn('kind', Types::STRING, ['length' => 32, 'notnull' => false]);
		}
		if (!$lists->hasColumn('is_active')) {
			$lists->addColumn('is_active', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
		}

		$items = $schema->getTable('bbml_list_items');
		if ($items->hasColumn('product_id') && $items->getColumn('product_id')->getNotnull()) {
			$items->modifyColumn('product_id', ['notnull' => false]);
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->normalizeArchivedLists();
		$this->backfillKinds();
		$this->activateNewestToBuyListPerOwner();
		$this->convertToBuyItemsToPlainText();
	}

	private function normalizeArchivedLists(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('bbml_lists')
			->set('status', $qb->createNamedParameter('finished', IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('status', $qb->createNamedParameter('archived', IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}

	private function backfillKinds(): void {
		$this->backfillKindFromFlag('INCOME', 'is_income');
		$this->backfillKindFromFlag('SUBSCRIPTION', 'is_subscription');

		$qb = $this->db->getQueryBuilder();
		$qb->update('bbml_lists')
			->set('kind', $qb->createNamedParameter('PURCHASE', IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('status', $qb->createNamedParameter('finished', IQueryBuilder::PARAM_STR)))
			->andWhere($this->kindIsUnsetExpression($qb));
		$qb->executeStatement();

		$qb = $this->db->getQueryBuilder();
		$qb->update('bbml_lists')
			->set('kind', $qb->createNamedParameter('NEED_TO_BUY', IQueryBuilder::PARAM_STR))
			->where($this->kindIsUnsetExpression($qb));
		$qb->executeStatement();
	}

	private function backfillKindFromFlag(string $kind, string $column): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('bbml_lists')
			->set('kind', $qb->createNamedParameter($kind, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq($column, $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->andWhere($this->kindIsUnsetExpression($qb));
		$qb->executeStatement();
	}

	private function kindIsUnsetExpression(IQueryBuilder $qb): ICompositeExpression {
		return $qb->expr()->orX(
			$qb->expr()->isNull('kind'),
			$qb->expr()->eq('kind', $qb->createNamedParameter('', IQueryBuilder::PARAM_STR)),
		);
	}

	private function activateNewestToBuyListPerOwner(): void {
		$activeOwners = [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('owner')
			->from('bbml_lists')
			->where($qb->expr()->eq('is_active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)));
		$result = $qb->executeQuery();
		/** @var list<array{owner: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();
		foreach ($rows as $row) {
			$activeOwners[$row['owner']] = true;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'owner')
			->from('bbml_lists')
			->where($qb->expr()->eq('kind', $qb->createNamedParameter('NEED_TO_BUY', IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC');
		$result = $qb->executeQuery();
		/** @var list<array{id: string, owner: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		$activated = [];
		foreach ($rows as $row) {
			$owner = $row['owner'];
			if ($owner === '' || isset($activeOwners[$owner]) || isset($activated[$owner])) {
				continue;
			}
			$activated[$owner] = true;
			$update = $this->db->getQueryBuilder();
			$update->update('bbml_lists')
				->set('is_active', $update->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
				->where($update->expr()->eq('id', $update->createNamedParameter($row['id'], IQueryBuilder::PARAM_STR)));
			$update->executeStatement();
		}
	}

	private function convertToBuyItemsToPlainText(): void {
		$listIds = $this->findToBuyListIds();
		if ($listIds === []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('i.id', 'i.custom_name')
			->selectAlias('p.name', 'product_name')
			->from('bbml_list_items', 'i')
			->leftJoin('i', 'bbml_products', 'p', $qb->expr()->eq('i.product_id', 'p.id'))
			->where($qb->expr()->in('i.list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$result = $qb->executeQuery();
		/** @var list<array{id: string, custom_name: ?string, product_name: ?string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		foreach ($rows as $row) {
			$customName = $row['custom_name'];
			if ($customName !== null && $customName !== '') {
				continue;
			}
			if ($row['product_name'] === null) {
				continue;
			}
			$update = $this->db->getQueryBuilder();
			$update->update('bbml_list_items')
				->set('custom_name', $update->createNamedParameter($row['product_name'], IQueryBuilder::PARAM_STR))
				->where($update->expr()->eq('id', $update->createNamedParameter($row['id'], IQueryBuilder::PARAM_STR)));
			$update->executeStatement();
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('bbml_list_items')
			->set('product_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('quantity', $qb->createNamedParameter(1.0, IQueryBuilder::PARAM_STR))
			->set('price', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->set('discount', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->in('list_id', $qb->createNamedParameter($listIds, IQueryBuilder::PARAM_STR_ARRAY)));
		$qb->executeStatement();
	}

	/**
	 * @return list<string>
	 */
	private function findToBuyListIds(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('bbml_lists')
			->where($qb->expr()->eq('kind', $qb->createNamedParameter('NEED_TO_BUY', IQueryBuilder::PARAM_STR)));
		$result = $qb->executeQuery();
		/** @var list<array{id: string}> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();

		return array_map(static fn (array $row): string => $row['id'], $rows);
	}
}
