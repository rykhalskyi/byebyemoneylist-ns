<script setup lang="ts">
import SpendingValue from './SpendingValue.vue'
import { monthRange } from '../../../utils/dashboardRange.ts'

const range = monthRange()
</script>

<template>
	<SpendingValue :from="range.from" :to="range.to" />
</template>
