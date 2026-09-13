<script setup lang="ts">
import { imagePath } from '@nextcloud/router'
import { inject } from 'vue'
import { t } from '../../../utils/l10n.ts'

const appVersion = inject<string>('appVersion', '')
const logo = imagePath('byebyemoneylist', 'bbml-logo.png')
</script>

<template>
	<div :class="$style.info">
		<img
			:src="logo"
			:alt="t('Bye Bye Money List')"
			:class="$style.logo"
			width="48"
			height="48">
		<p :class="$style.version">
			{{ t('Version {version}', { version: appVersion }) }}
		</p>
	</div>
</template>

<style module>
.info {
	display: flex;
	align-items: center;
	gap: 12px;
}

.logo {
	width: 48px;
	height: 48px;
}

.version {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
