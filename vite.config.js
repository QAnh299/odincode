import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/css/auth.css", "resources/css/sidebar.css", "resources/css/theme.css", "resources/css/topbar.css", "resources/css/profile.css", "resources/css/voucher.css", "resources/css/course.css", "resources/css/lead.css", "resources/css/opportunity.css", "resources/js/app.js"],
            refresh: true,
        }),
    ],
});
