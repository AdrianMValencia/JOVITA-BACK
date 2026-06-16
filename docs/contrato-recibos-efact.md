# Contrato recibos + eFact (POS / SUNAT)

## 1. Ticket POS vs CPE SUNAT

| Concepto | Columnas / origen | Uso |
|----------|---------------------|-----|
| Ticket interno (caja / POS) | `tbl_recibos.series`, `tbl_recibos.numeracion` | Impresión ticket, correlativo caja, listados `ticket_pos`, `enumeracion_ticket`. |
| Comprobante electrónico (SUNAT) | `tbl_recibos.efact_comprobante_serie`, `tbl_recibos.efact_comprobante_numero` (8 dígitos) | Texto tipo `BE01-00000023`, `comprobante_electronico`, PDF/XML eFact. |
| Ticket OSE | `tbl_recibos.efact_ticket` (UUID) | Descargas `GET /api/efact/pdf?...`, `.../efact/pdf/{ticket}`. |

No se debe persistir el correlativo SUNAT con ceros en `numeracion` del recibo.

### GET `/api/recibos/numeracion`

- **Sin** `serieComprobante` / `serie_comprobante_efact`: correlativo **ticket POS** (`alcance`: `ticket_pos_caja`).
- **Con** `serieComprobante` (ej. BE01): siguiente correlativo **CPE SUNAT** (`alcance`: `correlativo_cpe_sunat`), según último emitido en `tbl_facturacion` y `tbl_recibos` (equivalente a `GET /api/comprobantes/numeracion`).

## 2. Campos aceptados en POST `/api/recibos` (CPE)

**Canónico (snake_case):** `serie_comprobante_efact`, `numero_comprobante_efact`.

**Alias (camelCase, compatibilidad front):** `serieComprobanteEfact`, `numeroComprobanteEfact`, `serieComprobante_efact`, `numeroComprobante_efact`.

Si se envían pares completos de cualquiera de los nombres anteriores, el backend usa ese par solo para CPE y para generar el JSON/XML de eFact.

Para emitir sin esos campos, hace falta **`serieComprobante` + `numeroComprobante`** explícitos del CPE (no usar `series`/`numeracion` del ticket como correlativo SUNAT).

### Ticket POS en el body

- `serieTicket` o `series`: serie del ticket (preferido `serieTicket`).
- `numeracionTicket` o `numeracion`: correlativo del ticket (preferido `numeracionTicket`).

## 3. Respuesta POST recibos (éxito / 207)

En la **raíz** y dentro del objeto **`recibos`** (array merge; el front puede fusionar):

- `efact_ticket`: UUID devuelto por la OSE al enviar, si la emisión se intentó.
- **Alias del mismo UUID:** `ose_ticket`, `ticket_ose`, `efactTicket` (evitan heurísticas en el front).
- `comprobante_emitido`: texto `SERIE-000000NN`.
- `comprobante_electronico`: `{ "serie", "numero", "comprobante" }`.
- `efact_pdf_disponible`: `true` solo si la emisión a OSE respondió éxito en la misma petición. Si la OSE aún no genera PDF, `GET .../efact/pdf` puede responder error HTTP (p. ej. 404/412 según cuerpo OSE); el front debe usar PDF ticket interno y avisar.

El PDF firmado **no** viaja en el cuerpo del POST; se obtiene por GET contra la OSE con el ticket.

### Query aceptados en GET `/api/efact/pdf` (y xml/cdr por query)

Mismo UUID OSE; se acepta indistintamente: **`ticket`**, **`efact_ticket`**, **`efactTicket`**.

Con `?origen=recibo|comprobante&id=…` el backend usa solo **`efact_ticket` en tabla**, o **`tbl_efact_logs`** vinculado al id; **no** infiere ticket por serie/número POS ni por “gemelo” facturación (evita PDF incorrecto).

## 4. Listados

- `POST .../buscarPorFecha`: enriquecimiento vía `EfactReciboVisorEnrichment` (`ticket_pos`, `cpe_sunat`, `enumeracion_ticket`, etc.).
- `GET .../efact/emisiones`: ticket POS y CPE separados en la respuesta.

## 5. Preguntas frecuentes (backend)

### ¿`numeracionTickets.numeroActual` es el último usado o el siguiente?

Para la serie de **ticket POS** (`tbl_series_tickets` / `tbl_numeracion_tickets` vinculada al recibo), `numeroActual` es el **último correlativo POS emitido** (`N` tras guardar un recibo con `numeracion` = `N`). Los clientes que lean `GET .../numeracionTickets/{idPuntoVenta}` y muestren “siguiente” como `numeroActual + 1` quedan alineados.

El resolver de `POST /api/recibos` y `GET .../recibos/numeracion` (sin `serieComprobante` CPE) compara con el máximo en `tbl_recibos` y, **solo como compatibilidad** con filas aún no actualizadas, si en BD aparece `numeroActual === último_recibo + 1` (antigua semántica “siguiente guardado”), se interpreta como que el último usado es `último_recibo`.

`GET .../recibos/numeracion` en alcance `ticket_pos_caja` devuelve `siguiente` listo para emitir, `numeroActual_es_ultimo_correlativo_usado: true` y `numeroActual_es_siguiente_correlativo_disponible: false` respecto al valor crudo de `numeroActual_bd`.

### ¿`idSeries` en recibo apunta a ticket o a comprobante?

A **`tbl_series_tickets`** (serie de **ticket / caja** del punto de venta), no a la serie SUNAT del CPE.

### ¿El PDF eFact está en la misma respuesta del POST?

**No.** El POST hace el envío a la OSE de forma **síncrona** y devuelve `efact_ticket` y metadatos del CPE cuando existen. El PDF se consulta después con `GET /api/efact/pdf/{ticket}` o `GET /api/efact/pdf?origen=recibo&id=...`. Mientras la OSE no lo tenga listo, ese GET puede fallar; el contrato de UX es fallback al PDF del ticket POS.
