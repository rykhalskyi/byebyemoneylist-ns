<script setup lang="ts">
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

export interface MenuItem {
	id: string
	label: string
	icon: string
}

const props = defineProps<{ items: MenuItem[], active: string }>()

const emit = defineEmits<{ select: [id: string] }>()

function onSelect(id: string) {
	emit('select', id)
}
</script>

<template>
	<NcAppNavigation>
		<template v-for="item in items" :key="item.id">
			<NcAppNavigationItem
				:name="item.label"
				:title="item.label"
				:active="props.active === item.id"
				@click="onSelect(item.id)">
				<template #icon>
					<NcIconSvgWrapper :path="item.icon" :size="20" />
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigation>
</template>
