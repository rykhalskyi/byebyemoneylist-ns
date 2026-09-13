<script setup lang="ts">
import SpendingValue from './SpendingValue.vue'
import { todayRange } from '../../../utils/dashboardRange.ts'

const range = todayRange()
</script>

<template>
	<SpendingValue :from="range.from" :to="range.to" />
</template>
