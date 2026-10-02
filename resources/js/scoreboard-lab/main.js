/**
 * Scoreboard Lab — نقطهٔ ورود مستقل (بدون Inertia/Pinia).
 * فقط تم Tailwind/رتم رسمی صفحه از app.css می‌آید تا کامپوننت‌ها
 * دقیقاً با همان توکن‌های rtds رندر شوند.
 */
import "../../css/app.css";
import { createApp } from "vue";
import App from "./App.vue";

createApp(App).mount("#lab");
