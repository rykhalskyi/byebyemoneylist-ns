## Analytics

*The Analytics page contain charts of monthly spendings*

All elements show monthly statistic for current month. User can click < > buttons to go forth and back in months.

### Account state card

This cart represent account state summary. It contains:
- Balance summary. Big numbers.
- Incomes
- Expenses 
- Comparing with previous month. Does balance go up or down?

### Overview

- Expenses by category pie-chart with drilldown. 
    - Pie-chart shows top level categories of expanses in selected month. 
    - Color of segments are categories color.
    - each segment that is more that 2% has a connected category emoji icon legend.
    - After click on segment, in the middle of pie chart is shown clicked category name, Percent of category share in pie-chart, and amount of expanses.
    - Double click on segment drill down into clicked category. This is new pie-chart for subcategories of double clicked category.
    - There's "Back" button to go back to the parent pie-chart
    - under piechart show chips with color, emoji icon, name and percentage of each category in piechart.
    
    **References:** /home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/analytics/AnalyticsScreen.kt
                /home/admin/Source/byebyemoneylist/app/src/main/java/com/otakeeesen/byebyemoneylist/ui/components/analytics/AnalyticsCharts.kt

- Summary of TOP 5 Stores by expanses
    - Bar chart with top 5 stores.
    - Bar chart has a legend with store names
    - Bar chart show sum for each store in top 5 on it.


- Summary of TOP 5 Shopping lists by expanses
    - Bar chart with top 5 lists.
    - Bar chart has a legend with list names
    - Bar chart show sum for each list in top 5 on it.


