import { BarChart, PieChart } from 'echarts/charts'
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'

use([
	CanvasRenderer,
	PieChart,
	BarChart,
	TooltipComponent,
	LegendComponent,
	GridComponent,
])
