<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeedSeeder extends Seeder
{
    private const POSTS = [
        [
            'title' => 'Bienvenidos al nuevo portal de Strategic Mates',
            'body' => 'Nos complace anunciar el lanzamiento oficial de nuestro nuevo portal. Este espacio está diseñado para centralizar toda la información, documentos y herramientas que necesitás para gestionar tu franquicia de manera eficiente. Explorá los módulos disponibles y no dudes en contactarnos si tenés alguna consulta.',
            'type' => 'announcement',
            'visibility' => 'global',
            'is_pinned' => true,
        ],
        [
            'title' => 'Nuevo programa de capacitación: Apertura de Sucursales',
            'body' => 'Lanzamos un programa completo de capacitación enfocado en el proceso de apertura de nuevas sucursales. El programa incluye materiales en video, guías paso a paso y sesiones en vivo con nuestros consultores especializados. La inscripción estará disponible a partir del lunes próximo.',
            'type' => 'training',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Actualización en los contratos de franquicia 2026',
            'body' => 'Informamos que los modelos de contrato de franquicia han sido actualizados para cumplir con la nueva normativa de EE.UU. vigente a partir del 1 de enero de 2026. Por favor revisá los documentos en el módulo de contratos y coordina con tu asesor legal para la firma de las addendas correspondientes.',
            'type' => 'alert',
            'visibility' => 'global',
            'is_pinned' => true,
        ],
        [
            'title' => 'Novedades en el módulo de contabilidad',
            'body' => 'El módulo de contabilidad ahora cuenta con integración de IA para clasificar automáticamente las transacciones bancarias. Las entradas con confianza menor al 70% quedarán pendientes de revisión manual. Probá la nueva funcionalidad importando tus extractos bancarios del mes.',
            'type' => 'news',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Guía rápida: Cómo usar el editor BPMN',
            'body' => 'Publicamos una guía completa para usar el editor de mapas de proceso BPMN integrado en el portal. Aprendé a crear, editar y compartir tus flujos de trabajo en español e inglés. La guía incluye ejemplos prácticos basados en los procesos más comunes en franquicias gastronómicas.',
            'type' => 'training',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Mantenimiento programado: sábado 10 de mayo',
            'body' => 'El portal estará en mantenimiento el sábado 10 de mayo de 2:00 a.m. a 6:00 a.m. (EST). Durante ese período el acceso estará suspendido temporalmente. Planificá tus actividades con anticipación y descargá los documentos que necesités antes de esa fecha.',
            'type' => 'alert',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Resultados del Q1 2026: crecimiento del 23%',
            'body' => 'Nos complace compartir que la red de franquicias Strategic Mates registró un crecimiento del 23% en ingresos durante el primer trimestre de 2026. Este resultado supera las proyecciones iniciales y consolida nuestra posición como la consultora líder para negocios latinos en EE.UU.',
            'type' => 'news',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Checklist de cumplimiento fiscal Q2 2026',
            'body' => 'Recordamos a todos los franquiciados que el plazo para la presentación de declaraciones fiscales del Q1 vence el 15 de mayo. Adjuntamos el checklist de documentos requeridos. Si necesitás asistencia con la preparación de tus declaraciones, contactá a tu asesor asignado.',
            'type' => 'announcement',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Webinar: Marketing digital para pequeñas empresas',
            'body' => 'Este miércoles a las 7:00 p.m. EST realizaremos un webinar gratuito sobre estrategias de marketing digital adaptadas para pequeñas empresas latinas en EE.UU. Los temas incluirán: redes sociales, Google Business Profile y campañas de email. Registrate en el módulo de calendario.',
            'type' => 'training',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Nueva herramienta: Simulador de costos de franquicia',
            'body' => 'Presentamos el simulador de costos integrado en el portal. Esta herramienta te permite proyectar los costos de apertura, operación mensual y punto de equilibrio de una nueva sucursal. Los resultados se generan en tiempo real basándose en datos históricos de la red.',
            'type' => 'news',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Recordatorio: Actualización de datos de contacto',
            'body' => 'Solicitamos a todos los usuarios del portal que verifiquen y actualicen su información de contacto en el módulo de perfil. Esto es especialmente importante para garantizar que reciban notificaciones importantes sobre contratos, vencimientos y novedades de la franquicia.',
            'type' => 'announcement',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Caso de éxito: Tacos El Rey — de emprendimiento a franquicia',
            'body' => 'Compartimos la historia de José Martínez, quien en 2023 comenzó con un pequeño restaurante en Miami y hoy gestiona 3 sucursales como franquiciado Strategic Mates. En esta nota describe cómo el portal le ayudó a sistematizar sus procesos y escalar su negocio de manera ordenada.',
            'type' => 'news',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Módulo de inventario: nuevas funciones de alerta de stock',
            'body' => 'Actualizamos el módulo de inventario con alertas automáticas por email cuando el stock de un producto cae por debajo del umbral mínimo configurado. También agregamos la posibilidad de generar órdenes de compra desde el mismo módulo para agilizar la reposición.',
            'type' => 'news',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Capacitación obligatoria: Cumplimiento y ética empresarial',
            'body' => 'A partir del 1 de junio es obligatorio para todos los franquiciados y sus equipos completar el módulo de capacitación sobre cumplimiento normativo y ética empresarial. El curso tiene una duración estimada de 3 horas y estará disponible en el portal a partir de la próxima semana.',
            'type' => 'training',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Alerta de seguridad: Intentos de phishing detectados',
            'body' => 'Detectamos intentos de phishing dirigidos a franquiciados de nuestra red. Los mensajes fraudulentos intentan hacerse pasar por comunicaciones oficiales de Strategic Mates solicitando credenciales de acceso. Recordamos que NUNCA enviamos solicitudes de contraseña por email. Ante cualquier duda, contactá a soporte.',
            'type' => 'alert',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Encuesta de satisfacción del portal — 5 minutos',
            'body' => 'Queremos escucharte. Completá nuestra encuesta de satisfacción del portal y ayudanos a mejorar la experiencia para toda la red. Solo toma 5 minutos. Entre todos los participantes sortearemos una sesión de consultoría gratuita con uno de nuestros expertos senior.',
            'type' => 'announcement',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Guía de onboarding para nuevos empleados',
            'body' => 'Publicamos una guía completa de onboarding para incorporar nuevos integrantes a tu equipo. El material cubre los procesos estándar de la franquicia, el uso del portal, las políticas de atención al cliente y los indicadores clave de desempeño que se monitorean en el sistema de seguimiento.',
            'type' => 'training',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Cambios en la política de precios para proveedores homologados',
            'body' => 'Informamos que a partir del 1 de julio entran en vigencia los nuevos acuerdos de precios negociados con los proveedores homologados de la red. Los nuevos precios representan un ahorro promedio del 12% respecto a las condiciones anteriores. Los detalles están disponibles en el catálogo de servicios.',
            'type' => 'announcement',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Actualización del mapa de proceso: Atención al cliente',
            'body' => 'El mapa de proceso estándar para atención al cliente fue revisado y actualizado incorporando las mejores prácticas identificadas durante las auditorías de Q1. El nuevo flujo incluye pasos específicos para la gestión de reclamos online y la derivación a soporte especializado.',
            'type' => 'news',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
        [
            'title' => 'Recordatorio final: cierre de ejercicio fiscal',
            'body' => 'Quedan 7 días para el cierre del ejercicio fiscal. Asegurate de tener todas las transacciones del período cargadas y conciliadas en el módulo de contabilidad. El equipo de soporte financiero estará disponible en horario extendido (8 a.m.–10 p.m. EST) durante los próximos 7 días.',
            'type' => 'alert',
            'visibility' => 'global',
            'is_pinned' => false,
        ],
    ];

    public function run(): void
    {
        $author = User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->first();

        if (! $author) {
            $this->command->warn('FeedSeeder: No superadmin found — skipping post seeding.');

            return;
        }

        $now = now();

        foreach (self::POSTS as $index => $post) {
            DB::table('posts')->insertOrIgnore([
                'author_id' => $author->id,
                'franchise_id' => null,
                'title' => $post['title'],
                'body' => $post['body'],
                'type' => $post['type'],
                'visibility' => $post['visibility'],
                'is_pinned' => $post['is_pinned'],
                'image_url' => null,
                'file_url' => null,
                'file_name' => null,
                'published_at' => $now->copy()->subHours($index * 3),
                'created_at' => $now->copy()->subHours($index * 3),
                'updated_at' => $now->copy()->subHours($index * 3),
            ]);
        }

        $this->command->info('FeedSeeder: '.count(self::POSTS).' posts seeded.');
    }
}
