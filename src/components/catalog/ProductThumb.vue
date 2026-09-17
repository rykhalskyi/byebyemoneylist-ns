<script setup lang="ts">
import type { Category, Product } from '../../types.ts'

import { mdiPackageVariant } from '@mdi/js'
import { onMounted, ref, watch } from 'vue'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import CategoryBubble from './CategoryBubble.vue'
import { fetchProductPicture } from '../../services/listsApi.ts'

const props = defineProps<{
	product: Product | null
	category?: Category | null
}>()

const pictureCache = new Map<string, string | null>()

const pictureUrl = ref<string | null>(null)

async function loadPicture(): Promise<void> {
	const product = props.product
	if (!product?.hasPicture) {
		pictureUrl.value = null
		return
	}
	if (pictureCache.has(product.id)) {
		pictureUrl.value = pictureCache.get(product.id) ?? null
		return
	}
	try {
		const picture = await fetchProductPicture(product.id)
		pictureCache.set(product.id, picture?.dataUrl ?? null)
		pictureUrl.value = picture?.dataUrl ?? null
	} catch {
		pictureCache.set(product.id, null)
		pictureUrl.value = null
	}
}

onMounted(loadPicture)
watch(() => [props.product?.id, props.product?.hasPicture], loadPicture)
</script>

<template>
	<span :class="$style.thumb">
		<img
			v-if="pictureUrl"
			:src="pictureUrl"
			:alt="props.product?.name ?? ''"
			:class="$style.image">
		<CategoryBubble
			v-else-if="props.category"
			:color="props.category.color"
			:emoji="props.category.emoji" />
		<NcIconSvgWrapper v-else :path="mdiPackageVariant" :size="20" />
	</span>
</template>

<style module>
.thumb {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	overflow: hidden;
}

.image {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
</style>
