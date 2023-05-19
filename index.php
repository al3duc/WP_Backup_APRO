<?php
/**
 * Plugin Name: Copias de Seguridad
 * Description: Crea y restaura copias de seguridad de tu sitio en WordPress.
 * Version: 1.1
 * Author: Tu Nombre
 */

// Agregar una página de configuración al menú de WordPress
function copias_seguridad_menu() {
    add_menu_page(
        'Copias de Seguridad',
        'Copias de Seguridad',
        'manage_options',
        'copias_seguridad',
        'copias_seguridad_page',
        'dashicons-shield',
        80
    );
}
add_action('admin_menu', 'copias_seguridad_menu');

// Página de configuración de copias de seguridad
function copias_seguridad_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos suficientes para acceder a esta página.');
    }

    // Manejar la solicitud de copia de seguridad
    if (isset($_POST['action']) && $_POST['action'] === 'crear_copia') {
        crear_copia_seguridad();
    }

    // Manejar la solicitud de restaurar copia de seguridad
    if (isset($_POST['action']) && $_POST['action'] === 'restaurar_copia') {
        restaurar_copia_seguridad();
    }

    // Manejar la solicitud de eliminar copia de seguridad
    if (isset($_GET['action']) && $_GET['action'] === 'eliminar' && isset($_GET['archivo'])) {
        eliminar_copia_seguridad($_GET['archivo']);
    }

    // Manejar la solicitud de descargar copia de seguridad
    if (isset($_GET['action']) && $_GET['action'] === 'descargar' && isset($_GET['archivo'])) {
        descargar_copia_seguridad($_GET['archivo']);
    }

    // Obtener la lista de copias de seguridad existentes
    $ruta_copias = WP_CONTENT_DIR . '/copias_seguridad/';
    $copias_seguridad = obtener_copias_seguridad($ruta_copias);

    // Mostrar la página de configuración
    ?>
    <div class="wrap">
        <h1>Copias de Seguridad</h1>
        <form method="post" action="">
            <input type="hidden" name="action" value="crear_copia" />
            <p>
                <input type="submit" class="button button-primary" value="Crear Copia de Seguridad" />
            </p>
        </form>

        <hr />

        <h2>Restaurar Copia de Seguridad</h2>
        <form method="post" action="">
            <input type="hidden" name="action" value="restaurar_copia" />
            <p>
                <label for="archivo_copia">Archivo de copia de seguridad:</label>
                <input type="file" name="archivo_copia" accept=".zip" />
            </p>
            <p>
                <input type="submit" class="button button-primary" value="Restaurar Copia de Seguridad" />
            </p>
        </form>

        <hr />

        <h2>Lista de Copias de Seguridad</h2>
        <?php if (!empty($copias_seguridad)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nombre del archivo</th>
                        <th>Tamaño</th>
                        <th>Fecha de creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($copias_seguridad as $copia_seguridad) : ?>
                        <tr>
                        <td><?php echo $copia_seguridad['nombre']; ?></td>
                        <td><?php echo size_format($copia_seguridad['tamaño']); ?></td>
                        <td><?php echo $copia_seguridad['fecha']; ?></td>
                        <td>
                        <a href="<?php echo admin_url('admin.php?page=copias_seguridad&action=descargar&archivo=' . $copia_seguridad['nombre']); ?>" class="button">Descargar</a>
                        <a href="<?php echo admin_url('admin.php?page=copias_seguridad&action=eliminar&archivo=' . $copia_seguridad['nombre']); ?>" class="button" onclick="return confirm('¿Estás seguro de que deseas eliminar esta copia de seguridad?')">Eliminar</a>
                        </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        </table>
                        <?php else : ?>
                        <p>No hay copias de seguridad disponibles.</p>
                        <?php endif; ?>
                        </div>
                        <?php
}

// Función para obtener la lista de copias de seguridad existentes
function obtener_copias_seguridad($ruta_copias) {
    $copias_seguridad = [];    
    
    if (is_dir($ruta_copias)) {
        $archivos = glob($ruta_copias . '*.zip');
    
        foreach ($archivos as $archivo) {
            $nombre_archivo = basename($archivo);
            $tamaño_archivo = filesize($archivo);
            $fecha_archivo = date('Y-m-d H:i:s', filemtime($archivo));
    
            $copias_seguridad[] = array(
                'nombre' => $nombre_archivo,
                'tamaño' => $tamaño_archivo,
                'fecha' => $fecha_archivo
            );
        }
    }
    
    return $copias_seguridad;
}
    
// Función para eliminar una copia de seguridad
function eliminar_copia_seguridad($archivo) {
    $ruta_copias = WP_CONTENT_DIR . '/copias_seguridad/';
    $archivo_eliminar = $ruta_copias . $archivo;    
 
    if (file_exists($archivo_eliminar)) {
        unlink($archivo_eliminar);
        echo '<div class="notice notice-success"><p>Copia de seguridad eliminada correctamente.</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>No se pudo eliminar la copia de seguridad.</p></div>';
    }
}

// Función para descargar una copia de seguridad
function descargar_copia_seguridad($archivo) {
    $ruta_copias = WP_CONTENT_DIR . '/copias_seguridad/';
    $archivo_descargar = $ruta_copias . $archivo;

    if (file_exists($archivo_descargar)) {
        $archivo_url = WP_CONTENT_URL . '/copias_seguridad/' . $archivo;
        echo "<script>window.location = '$archivo_url';</script>";
        exit;
    } else {
        echo '<div class="notice notice-error"><p>No se pudo descargar la copia de seguridad.</p></div>';
    }
}


function crear_copia_seguridad() {
    $directorio = WP_CONTENT_DIR . '/copias_seguridad/';
    $archivo_local = $directorio . 'backup-' . date('Y-m-d-H-i-s') . '.zip';

    // Crear respaldo de la base de datos
    $archivo_sql = $directorio . 'database.sql';
    crear_respaldo_base_datos($archivo_sql);

    // Comprimir los archivos y el respaldo de la base de datos en un archivo maestro
    $zip = new ZipArchive();
    if ($zip->open($archivo_local, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        agregar_archivo_a_zip($archivo_sql, 'database.sql', $zip);
        agregar_directorio_a_zip(get_home_path(), '', $zip);
        $zip->close();
    } else {
        echo '<div class="notice notice-error"><p>No se pudo crear la copia de seguridad.</p></div>';
    }
}

function crear_respaldo_base_datos($archivo_sql) {
    global $wpdb;

    $database_name = DB_NAME;
    $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
    $sql = "";

    foreach ($tables as $table) {
        $table_name = $table[0];
        $sql .= "DROP TABLE IF EXISTS $table_name;";
        $table_structure = $wpdb->get_results("SHOW CREATE TABLE $table_name", ARRAY_N);
        $sql .= "\n\n" . $table_structure[0][1] . ";\n\n";

        $rows = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        foreach ($rows as $row) {
            $row = array_map('addslashes', $row);
            $row = array_map('htmlentities', $row);
            $row_values = implode("','", array_values($row));
            $sql .= "INSERT INTO $table_name VALUES ('$row_values');\n";
        }

        $sql .= "\n\n";
    }

    file_put_contents($archivo_sql, $sql);
}

function agregar_archivo_a_zip($archivo, $nombre_en_zip, $zip) {
    $zip->addFile($archivo, $nombre_en_zip);
}

function agregar_directorio_a_zip($directorio, $directorio_base, $zip) {
    $archivos = scandir($directorio);

    foreach ($archivos as $archivo) {
        if ($archivo === '.' || $archivo === '..') {
            continue;
        }

        $ruta_completa = $directorio . '/' . $archivo;
        $nombre_en_zip = ltrim($directorio_base . '/' . $archivo, '/');

        if (is_dir($ruta_completa)) {
            $zip->addEmptyDir($nombre_en_zip);
            agregar_directorio_a_zip($ruta_completa, $nombre_en_zip, $zip);
        } else {
            $zip->addFile($ruta_completa, $nombre_en_zip);
        }
    }
}

// Función para restaurar una copia de seguridad
function restaurar_copia_seguridad($archivo_zip) {
    $directorio_destino = ABSPATH;

    // Extraer archivos del archivo ZIP
    $zip = new ZipArchive;
    if ($zip->open($archivo_zip) === true) {
        $zip->extractTo($directorio_destino);
        $zip->close();
        echo '<div class="notice notice-success"><p>La copia de seguridad se ha restaurado correctamente.</p></div>';

        // Restaurar la base de datos
        $archivo_sql = $directorio_destino . 'database.sql';
        if (file_exists($archivo_sql)) {
            restaurar_base_datos($archivo_sql);
        }
    } else {
        echo '<div class="notice notice-error"><p>No se pudo restaurar la copia de seguridad.</p></div>';
    }
}

function restaurar_base_datos($archivo_sql) {
    global $wpdb;
    $sql = file_get_contents($archivo_sql);
    $queries = preg_split('/;\s*[\r\n]+/', $sql);

    foreach ($queries as $query) {
        if (!empty($query)) {
            $wpdb->query($query);
        }
    }
}