<script setup lang="ts">
import { mdiCartPlus } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { t } from '../../../utils/l10n.ts'

defineEmits<{ purchase: ['manual'] }>()
</script>

<template>
	<NcButton type="button" variant="primary" @click="$emit('purchase', 'manual')">
		<template #icon>
			<NcIconSvgWrapper :path="mdiCartPlus" :size="20" />
		</template>
		{{ t('Add purchase') }}
	</NcButton>
</template>
