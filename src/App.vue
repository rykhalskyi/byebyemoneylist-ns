<script setup lang="ts">
import { computed, ref } from 'vue'
import { mdiViewDashboard, mdiCart, mdiChartPie, mdiPackageVariant, mdiCog } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import Menu from './components/Menu.vue'
import ShoppingLists from './views/ShoppingLists.vue'

const items = [
	{ id: 'dashboard', label: 'Dashboard', icon: mdiViewDashboard },
	{ id: 'lists', label: 'Shopping Lists', icon: mdiCart },
	{ id: 'analytics', label: 'Analytics', icon: mdiChartPie },
	{ id: 'catalog', label: 'Catalog', icon: mdiPackageVariant },
	{ id: 'settings', label: 'Settings', icon: mdiCog },
]

const currentView = ref(items[0].id)

const currentLabel = computed(() => items.find((item) => item.id === currentView.value)?.label ?? currentView.value)

function onSelect(id: string) {
	currentView.value = id
}
</script>

<template>
	<NcContent app-name="byebyemoneylist">
		<Menu :items="items" @select="onSelect" />
		<NcAppContent :class="$style.content">
			<ShoppingLists v-if="currentView === 'lists'" />
			<h2 v-else>
				{{ currentLabel }}
			</h2>
		</NcAppContent>
	</NcContent>
</template>

<style module>
.content {
	display: flex;
	justify-content: center;
	margin: 16px;
}
</style>
