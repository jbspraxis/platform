// Chart.js for the two pages that draw a chart: Company overview and the finance Executive dashboard. One pinned copy
// (package.json), its own entry in the build (vite.config.js), and fetched by those pages only, with import(), after
// the page has shown; every other page never downloads it. Only what those charts use is registered: bar and line
// charts, their axes, the legend, tooltips and the filled area under a line.
import {
    Chart,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Filler,
    Legend,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Filler, Legend, Tooltip);

export default Chart;
