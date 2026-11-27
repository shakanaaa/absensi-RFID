use tauri::Manager;

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_sql::Builder::default().build())
        .setup(|app| {
            // Inisialisasi skema SQLite lokal — dipanggil dari frontend
            // melalui plugin-sql (db: "sqlite:absensi_terminal.db").
            if let Some(window) = app.get_webview_window("main") {
                let _ = window;
            }
            Ok(())
        })
        .run(tauri::generate_context!())
        .expect("error while running absensi terminal");
}
