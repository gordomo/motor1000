# WhatsApp: estado y pendientes

## Estado actual (octubre 2026)

**WhatsApp no está conectado.** En prod `WHATSAPP_PROVIDER` no está configurado,
así que se usa el proveedor `log`: los mensajes no salen, solo se escriben en el
log de Laravel.

Hasta el 2/10/2026 el sistema igual registraba esos mensajes como enviados. Por
eso en prod quedaron 152 turnos con `reminder_sent = 1` y 38 comunicaciones en
estado `sent` que **nunca llegaron al cliente**. Esos datos no se corrigieron.

Desde ese día:

- `CommunicationService::whatsappConectado()` devuelve `false` mientras el proveedor
  sea `log`. En ese caso no se crea la comunicación ni se marca el turno como avisado.
- Los recordatorios ya no "se mandan" solos. Aparecen en **Para hoy**
  (`/panel/para-hoy`) para que el usuario del sistema mande el mensaje a mano,
  con un link de `wa.me` que abre el chat con el texto ya escrito.
- El comando `reminders:process` se reemplazó por `para-hoy:avisar`, que corre a
  las 8:00 y deja el resumen del día en la campanita de admin y recepción.

## Qué hay en el código

| Pieza | Dónde |
|---|---|
| Elección del proveedor (`log` / `twilio`) | `AppServiceProvider::register()`, `config/services.php` (`whatsapp.provider`) |
| Interfaz de proveedor | `app/Services/WhatsApp/WhatsAppProviderInterface.php` |
| Proveedor Twilio (sin probar en prod) | `app/Services/WhatsApp/TwilioWhatsAppProvider.php`, variables `TWILIO_*` |
| Envío y registro en `communications` | `CommunicationService::send()` → `SendCommunicationJob` (cola en Redis/Horizon) |
| Plantillas editables por taller | Recurso "Plantillas de comunicación" (`communication_templates`) |
| Links manuales `wa.me` (Argentina) | `App\Support\WhatsAppLink` |

Mensajes que saldrían solos cuando WhatsApp esté conectado:

- **Aviso de turno el día anterior.** `SendAppointmentReminderJob`, corre cada hora.
- **Vehículo listo para retirar.** `CommunicationService::notifyVehicleReady()`, se dispara al completar una orden.

Mensajes que hoy se mandan a mano desde **Para hoy**, y que se podrían automatizar:

- saludo de cumpleaños;
- recordatorios de mantenimiento;
- pedido de confirmación del turno de mañana.

## Para conectarlo (pendiente)

Decisiones a tomar:

1. **Proveedor.**
   - **WhatsApp Cloud API de Meta directo.** Habría que escribir un proveedor nuevo. No hay intermediario que cobre por mensaje, y los mensajes entrantes llegan con los datos de la campaña de Facebook de la que vino el cliente (ver "Leads" más abajo).
   - **Twilio.** El proveedor ya está escrito, pero cobra por mensaje encima de lo que cobra Meta.
2. **Número.** Hay que decidir qué número usar: uno nuevo, o el que el taller ya usa en la app de WhatsApp Business. Hay que revisar en Meta si se puede usar el mismo número en la app y en la API a la vez.
3. **Cuenta de Meta Business verificada.** El sitio ya muestra razón social y CUIT para esta verificación.
4. **Plantillas aprobadas por Meta.** Los mensajes que inicia el taller (recordatorios, cumpleaños, aviso de turno) tienen que ser plantillas aprobadas. El texto libre solo se puede mandar dentro de las 24 horas siguientes a que el cliente haya escrito.

Trabajo técnico, una vez decidido lo anterior:

- proveedor nuevo (si es Cloud API) y variables de entorno;
- un webhook para recibir mensajes y estados de entrega (`delivered`, `read`, `failed`), que hoy no existe;
- pasar los mensajes de `ParaHoy` a plantillas aprobadas y agregar un botón "Mandar" junto al link manual.

## Idea a futuro: leads de campañas de Facebook

Los anuncios de Facebook e Instagram que abren un chat de WhatsApp (Click-to-WhatsApp) mandan, en el primer mensaje que llega por el webhook, de qué anuncio vino la persona. Con el webhook andando se podría:

- crear el cliente o lead automáticamente, con el origen de la campaña;
- ver cuántos leads trae cada campaña y cuántos terminan en turno u orden.

Antes de implementarlo, confirmar en la documentación de Meta el formato exacto de esos datos.
