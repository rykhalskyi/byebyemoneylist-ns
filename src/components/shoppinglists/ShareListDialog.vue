<script setup lang="ts">
import type { ListShare, ShareMode, ShoppingList } from '../../types.ts'

import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { fetchListShares, revokeListShare, shareList } from '../../services/listsApi.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{ open: boolean, list: ShoppingList }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
}>()

interface ModeOption {
	id: ShareMode
	label: string
}

const modeOptions: ModeOption[] = [
	{ id: 'readonly', label: t('Read only') },
	{ id: 'readwrite', label: t('Read & write') },
]

const shares = ref<ListShare[]>([])
const user = ref('')
const mode = ref<ModeOption>(modeOptions[0])
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)

const canSubmit = computed(() => user.value.trim() !== '' && !submitting.value)

watch(
	() => props.open,
	(open) => {
		if (open) {
			user.value = ''
			mode.value = modeOptions[0]
			error.value = null
			submitting.value = false
			loadShares()
		}
	},
)

async function loadShares() {
	loading.value = true
	try {
		shares.value = await fetchListShares(props.list.id)
	} catch {
		error.value = t('Failed to load the shares.')
	} finally {
		loading.value = false
	}
}

async function onSubmit() {
	if (!canSubmit.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const share = await shareList(props.list.id, user.value.trim(), mode.value.id)
		shares.value = [...shares.value.filter((candidate) => candidate.sharedWith !== share.sharedWith), share]
		user.value = ''
	} catch {
		error.value = t('Failed to share the list. Please try again.')
	} finally {
		submitting.value = false
	}
}

async function revoke(share: ListShare) {
	submitting.value = true
	error.value = null
	try {
		const updated = await revokeListShare(props.list.id, share.id)
		shares.value = shares.value.map((candidate) => (candidate.id === updated.id ? updated : candidate))
	} catch {
		error.value = t('Failed to revoke the share.')
	} finally {
		submitting.value = false
	}
}

function modeLabel(value: ShareMode): string {
	return value === 'readwrite' ? t('Read & write') : t('Read only')
}

function onCancel() {
	emit('update:open', false)
}
</script>

<template>
	<NcDialog
		:name="t('Share list')"
		:open="props.open"
		size="normal"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<p :class="$style['list-name']">
				{{ props.list.name }}
			</p>

			<div :class="$style.fields">
				<NcTextField
					v-model="user"
					:label="t('User')"
					:placeholder="t('Username')"
					:disabled="submitting" />
				<NcSelect
					v-model="mode"
					label="label"
					:inputLabel="t('Access')"
					:options="modeOptions"
					:clearable="false"
					:disabled="submitting" />
			</div>

			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>

			<div v-if="shares.length > 0" :class="$style.shares">
				<p :class="$style['shares-title']">
					{{ t('Shared with') }}
				</p>
				<ul :class="$style['share-list']">
					<li v-for="share in shares" :key="share.id" :class="$style['share-row']">
						<span :class="[$style['share-user'], { [$style.muted]: share.revoked }]">{{ share.sharedWith }}</span>
						<NcChip :text="modeLabel(share.mode)" noClose />
						<NcButton
							v-if="!share.revoked"
							type="button"
							variant="tertiary"
							:disabled="submitting"
							@click="revoke(share)">
							{{ t('Revoke') }}
						</NcButton>
						<span v-else :class="$style.muted">{{ t('Revoked') }}</span>
					</li>
				</ul>
			</div>
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="onCancel">
				{{ t('Close') }}
			</NcButton>
			<NcButton
				type="button"
				variant="primary"
				:disabled="!canSubmit"
				@click="onSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ t('Share') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.list-name {
	font-weight: bold;
	margin: 0;
}

.fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

.error {
	color: var(--color-error);
	margin: 0;
}

.shares {
	border-top: 1px solid var(--color-border);
	padding-top: 12px;
}

.shares-title {
	color: var(--color-text-maxcontrast);
	margin: 0 0 8px;
}

.share-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.share-row {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
}

.share-user {
	flex: 1;
	min-width: 0;
}

.muted {
	color: var(--color-text-maxcontrast);
	font-style: italic;
}
</style>
