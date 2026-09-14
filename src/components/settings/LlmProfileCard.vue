<script setup lang="ts">
import type { LlmProfile } from '../../types.ts'

import { mdiKeyVariant, mdiPencil, mdiRobotOutline } from '@mdi/js'
import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { getProviderConfig } from '../../constants/llmProviders.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{
	profile: LlmProfile
	disabled?: boolean
}>()

const emit = defineEmits<{
	select: [profile: LlmProfile]
	edit: [profile: LlmProfile]
}>()

const providerConfig = computed(() => getProviderConfig(props.profile.provider))
const displayModel = computed(() => props.profile.model || providerConfig.value.defaultModel)
</script>

<template>
	<div
		:class="[
			$style.card,
			props.profile.isActive && $style['card--active'],
		]"
		@click="emit('select', props.profile)">
		<div :class="$style.radioContainer">
			<NcCheckboxRadioSwitch
				:modelValue="props.profile.isActive"
				type="radio"
				name="active-llm-profile"
				:disabled="props.disabled"
				:aria-label="t('Set as active profile')"
				@update:modelValue="emit('select', props.profile)" />
		</div>

		<div :class="$style.main">
			<div :class="$style.header">
				<h3 :class="$style.title">
					{{ props.profile.name }}
				</h3>
				<span :class="$style.badge">
					{{ providerConfig.label }}
				</span>
				<span v-if="props.profile.isActive" :class="$style.activeBadge">
					{{ t('Active') }}
				</span>
			</div>

			<div :class="$style.details">
				<span :class="$style.detailItem">
					<NcIconSvgWrapper :path="mdiRobotOutline" :size="16" />
					{{ displayModel }}
				</span>
				<span :class="$style.detailItem">
					<NcIconSvgWrapper :path="mdiKeyVariant" :size="16" />
					<code>{{ props.profile.apiKeyMasked }}</code>
				</span>
			</div>
		</div>

		<div :class="$style.actions" @click.stop>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="props.disabled"
				:aria-label="t('Edit profile')"
				@click="emit('edit', props.profile)">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPencil" :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<style module>
.card {
	display: flex;
	align-items: center;
	padding: 16px;
	border-radius: var(--border-radius-large, 12px);
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	box-shadow: var(--shadow-small, 0 1px 3px rgba(0, 0, 0, 0.08));
	cursor: pointer;
	transition: all 0.15s ease;
	gap: 16px;
}

.card:hover {
	border-color: var(--color-primary-element);
	background-color: var(--color-background-hover);
}

.card--active {
	border-color: var(--color-primary-element);
	background-color: var(--color-primary-element-light, rgba(0, 130, 201, 0.05));
}

.radioContainer {
	display: flex;
	align-items: center;
	justify-content: center;
}

.main {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.header {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.title {
	margin: 0;
	font-size: var(--font-size-large, 16px);
	font-weight: 600;
	color: var(--color-main-text);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.badge {
	padding: 2px 8px;
	font-size: var(--font-size-small, 12px);
	font-weight: 500;
	border-radius: 12px;
	background-color: var(--color-background-darker, #ebebeb);
	color: var(--color-text-maxcontrast);
}

.activeBadge {
	padding: 2px 8px;
	font-size: var(--font-size-small, 12px);
	font-weight: 600;
	border-radius: 12px;
	background-color: var(--color-primary-element);
	color: var(--color-primary-text, #fff);
}

.details {
	display: flex;
	align-items: center;
	gap: 16px;
	flex-wrap: wrap;
	font-size: var(--font-size-small, 13px);
	color: var(--color-text-maxcontrast);
}

.detailItem {
	display: flex;
	align-items: center;
	gap: 6px;
}

.detailItem code {
	background: none;
	padding: 0;
	font-family: monospace;
}

.actions {
	display: flex;
	align-items: center;
}
</style>
