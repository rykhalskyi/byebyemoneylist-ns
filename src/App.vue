<script setup lang="ts">
import { ref } from 'vue'
import { mdiViewDashboard, mdiCart, mdiChartPie, mdiPackageVariant, mdiCog } from '@mdi/js'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcContent from '@nextcloud/vue/components/NcContent'
import Menu from './components/Menu.vue'

const items = [
	{ id: 'dashboard', label: 'Dashboard', icon: mdiViewDashboard },
	{ id: 'lists', label: 'Shopping Lists', icon: mdiCart },
	{ id: 'analytics', label: 'Analytics', icon: mdiChartPie },
	{ id: 'catalog', label: 'Catalog', icon: mdiPackageVariant },
	{ id: 'settings', label: 'Settings', icon: mdiCog },
]

const currentView = ref(items[0].label)

function onSelect(id: string) {
	currentView.value = items.find((item) => item.id === id)?.label ?? ''
}
</script>

<template>
	<NcContent app-name="byebyemoneylist">
		<Menu :items="items" @select="onSelect" />
		<NcAppContent :class="$style.content">
			<h2>{{ currentView }}</h2>
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
