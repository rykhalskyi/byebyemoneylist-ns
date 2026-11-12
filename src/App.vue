<script setup lang="ts">
import { mdiCart, mdiChartPie, mdiCog, mdiInbox, mdiPackageVariant, mdiViewDashboard } from '@mdi/js'
import { computed, defineAsyncComponent, onMounted, ref } from 'vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import InitializeCategoriesDialog from './components/InitializeCategoriesDialog.vue'
import Menu from './components/Menu.vue'
import Catalog from './views/Catalog.vue'
import Dashboard from './views/Dashboard.vue'
import Imports from './views/Imports.vue'
import Settings from './views/Settings.vue'
import ShoppingLists from './views/ShoppingLists.vue'
import ToBuy from './views/ToBuy.vue'
import { useCategoryInitialization } from './composables/useCategoryInitialization.ts'
import { t } from './utils/l10n.ts'

const Analytics = defineAsyncComponent(() => import('./views/Analytics.vue'))

const items = [
	{ id: 'dashboard', label: t('Dashboard'), icon: mdiViewDashboard },
	{ id: 'lists', label: t('Shopping Lists'), icon: mdiCart },
	{ id: 'imports', label: t('Inbox'), icon: mdiInbox },
	{ id: 'analytics', label: t('Analytics'), icon: mdiChartPie },
	{ id: 'catalog', label: t('Catalog'), icon: mdiPackageVariant },
	{ id: 'settings', label: t('Settings'), icon: mdiCog },
]

const currentView = ref(items[0].id)
const purchaseIntent = ref<'manual' | 'scan' | null>(null)
const toBuyOpen = ref(false)

const { busy: initializing, error: initializeError, shouldPrompt, initialize } = useCategoryInitialization()
const showInitializeDialog = ref(false)

onMounted(async () => {
	if (await shouldPrompt()) {
		showInitializeDialog.value = true
	}
})

const currentLabel = computed(() => items.find((item) => item.id === currentView.value)?.label ?? currentView.value)

function onSelect(id: string) {
	currentView.value = id
}

async function onInitializeConfirm() {
	const created = await initialize()
	if (created !== null) {
		showInitializeDialog.value = false
		currentView.value = 'catalog'
	}
}

function openPurchase(mode: 'manual' | 'scan') {
	purchaseIntent.value = mode
	currentView.value = 'lists'
}
</script>

<template>
	<NcContent appName="byebyemoneylist">
		<Menu
			v-if="!toBuyOpen"
			:items="items"
			:active="currentView"
			@select="onSelect" />
		<NcAppContent :class="$style.content">
			<ToBuy v-if="toBuyOpen" @close="toBuyOpen = false" />
			<template v-else>
				<Dashboard v-if="currentView === 'dashboard'" @purchase="openPurchase" />
				<ShoppingLists
					v-else-if="currentView === 'lists'"
					:purchaseMode="purchaseIntent"
					@purchaseOpened="purchaseIntent = null"
					@openToBuy="toBuyOpen = true" />
				<Imports v-else-if="currentView === 'imports'" />
				<Analytics v-else-if="currentView === 'analytics'" />
				<Catalog v-else-if="currentView === 'catalog'" />
				<Settings v-else-if="currentView === 'settings'" />
				<h2 v-else>
					{{ currentLabel }}
				</h2>
			</template>
		</NcAppContent>
		<InitializeCategoriesDialog
			:open="showInitializeDialog"
			:busy="initializing"
			:error="initializeError"
			@update:open="showInitializeDialog = $event"
			@confirm="onInitializeConfirm" />
	</NcContent>
</template>

<style module>
.content {
	display: flex;
	justify-content: center;
	margin: 16px;
}
</style>
