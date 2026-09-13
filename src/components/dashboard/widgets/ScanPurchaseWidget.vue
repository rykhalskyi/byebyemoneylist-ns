<script setup lang="ts">
import { mdiReceiptText } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { t } from '../../../utils/l10n.ts'

defineEmits<{ purchase: ['scan'] }>()
</script>

<template>
	<NcButton type="button" variant="secondary" @click="$emit('purchase', 'scan')">
		<template #icon>
			<NcIconSvgWrapper :path="mdiReceiptText" :size="20" />
		</template>
		{{ t('Scan receipt') }}
	</NcButton>
</template>
