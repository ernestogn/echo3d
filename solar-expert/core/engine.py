import json
import math
from pathlib import Path

DATA_DIR = Path(__file__).resolve().parent.parent / "data"

with open(DATA_DIR / "irradiacion.json", "r", encoding="utf-8") as f:
    IRRADIACION = json.load(f)["argentina"]

RADIO_TIERRA_KM = 6371.0


def _dist_km(lat1, lon1, lat2, lon2):
    """Distancia haversine entre dos puntos en grados decimales."""
    phi1, phi2 = math.radians(lat1), math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dlambda = math.radians(lon2 - lon1)
    a = math.sin(dphi / 2) ** 2 + math.cos(phi1) * math.cos(phi2) * math.sin(dlambda / 2) ** 2
    return 2 * RADIO_TIERRA_KM * math.asin(math.sqrt(a))


def hsp_por_coordenadas(lat: float, lon: float) -> tuple[float, float, list[float], str]:
    """Interpola HSP entre provincias según distancia GPS.
    Devuelve (hsp_anual, hsp_peor_mes, hsp_por_mes, provincia_mas_cercana)."""
    distancias = []
    for nombre, prov in IRRADIACION.items():
        d = _dist_km(lat, lon, prov["lat"], -58.0)  # longitud promedio arg ~ -58
        distancias.append((d, nombre, prov))

    distancias.sort(key=lambda x: x[0])
    cercanas = distancias[:4]

    pesos = []
    hsp_anuales = []
    meses_totales = [0.0] * 12
    for d, nombre, prov in cercanas:
        w = 1.0 / (d ** 2 + 0.01)
        pesos.append(w)
        hsp_anuales.append((w, prov["promedio_anual"]))
        for i, v in enumerate(prov["hsp_mensual"]):
            meses_totales[i] += w * v

    hsp_anual = sum(w * v for w, v in hsp_anuales) / sum(pesos)
    hsp_peor = min(meses_totales) / max(pesos)
    prov_cercana = cercanas[0][1]
    return round(hsp_anual, 2), round(hsp_peor, 2), [round(v / max(pesos), 2) for v in meses_totales], prov_cercana

with open(DATA_DIR / "catalogo.json", "r", encoding="utf-8") as f:
    CATALOGO = json.load(f)


# ── Utilidades de búsqueda ──────────────────────────────────────────────────

def buscar_provincia(nombre: str) -> dict | None:
    clave = nombre.strip()
    return (
        IRRADIACION.get(clave)
        or IRRADIACION.get(clave.title())
        or IRRADIACION.get(clave.upper())
    )


# ── Selección paso a paso ────────────────────────────────────────────────────

def elegir_panel(panel_id: str | None, consumo_real_wh: float, hsp_anual: float) -> dict:
    """Selecciona panel. Si hay panel_id lo fuerza, si no elige el que
    ajuste mejor la cantidad objetivo entre 4 y 30 paneles."""
    if panel_id:
        for p in CATALOGO["paneles"]:
            if p["id"] == panel_id:
                return p, f"Forzado por usuario: {p['marca']} {p['modelo']} ({p['potencia_wp']} Wp)"
    # Elegir panel razonable (ni muy chico ni muy grande)
    candidatos = []
    for p in CATALOGO["paneles"]:
        energia = p["potencia_wp"] * hsp_anual * 0.87
        n = math.ceil(consumo_real_wh / energia)
        candidatos.append((p, n, abs(n - 10)))  # 10 como cantidad objetivo
    p, n, diff = min(candidatos, key=lambda x: x[2])
    return p, f"Selección automática: {p['marca']} {p['modelo']} ({p['potencia_wp']} Wp) → ~{n} paneles"


def elegir_bateria(tension_sistema: int, tipo: str) -> dict:
    if tipo == "LiFePO4":
        for b in CATALOGO["baterias"]:
            if b["tipo"] == "LiFePO4" and b["tension_nominal_v"] == tension_sistema:
                return b, f"Candidatas LiFePO4 de {tension_sistema}V: {b['marca']} {b['modelo']} ({b['capacidad_ah']} Ah, DoD {int(b['dod_max']*100)}%)"
    for b in CATALOGO["baterias"]:
        if b["tension_nominal_v"] == tension_sistema and b["tipo"] == tipo:
            return b, f"Candidata: {b['marca']} {b['modelo']} ({b['tipo']}, {b['capacidad_ah']} Ah)"
    raise ValueError(f"Sin baterías de {tension_sistema}V y tipo {tipo} en catálogo")


def elegir_inversor(tension_sistema: int, demanda_sim_w: float) -> tuple[dict, list]:
    candidatos = [
        inv for inv in CATALOGO["inversores"]
        if inv["tension_banco_v"] == tension_sistema and inv["potencia_nominal_w"] >= demanda_sim_w
    ]
    if not candidatos:
        candidatos = [inv for inv in CATALOGO["inversores"] if inv["tension_banco_v"] == tension_sistema]
        return candidatos[0], [f"⚠️ No hay inversor que cubra {demanda_sim_w:.0f}W en {tension_sistema}V. Usando {candidatos[0]['modelo']} ({candidatos[0]['potencia_nominal_w']}W) — no cubre picos."]
    elegido = candidatos[0]
    justificaciones = [
        f"Candidatas {tension_sistema}V que cubren {demanda_sim_w:.0f}W: {[inv['modelo'] + ' (' + str(inv['potencia_nominal_w']) + 'W)' for inv in candidatos]}",
        f"→ Seleccionado: {elegido['marca']} {elegido['modelo']} ({elegido['potencia_nominal_w']}W, pico {elegido['potencia_pico_w']}W, MPPT {elegido['rango_mppt_v'][0]}-{elegido['rango_mppt_v'][1]}V)",
    ]
    return elegido, justificaciones


def elegir_controlador(tension_sistema: int, corriente_paneles: float, voc_string: float) -> tuple[dict, list]:
    candidatos = [
        c for c in CATALOGO["controladores"]
        if c["tension_banco_v"] == tension_sistema
        and c["corriente_max_a"] >= corriente_paneles
        and c["voc_max_v"] >= voc_string * 1.05
    ]
    if not candidatos:
        candidatos = [
            c for c in CATALOGO["controladores"]
            if c["tension_banco_v"] == tension_sistema
        ]
        c = candidatos[0]
        return c, [f"⚠️ Ningún controlador cubre {corriente_paneles:.1f}A y Voc {voc_string:.0f}V. Usando {c['marca']} {c['modelo']} — verificar.", f"→ Seleccionado: {c['marca']} {c['modelo']} ({c['tipo']}, {c['corriente_max_a']}A, Voc máx {c['voc_max_v']}V)"]
    elegido = candidatos[0]
    justificaciones = [
        f"Candidatas: {[c['modelo'] + ' (' + str(c['corriente_max_a']) + 'A, Voc ' + str(c['voc_max_v']) + 'V)' for c in candidatos]}",
        f"→ Seleccionado: {elegido['marca']} {elegido['modelo']} ({elegido['tipo']}, {elegido['corriente_max_a']}A máx.)",
    ]
    return elegido, justificaciones


# ── Motor principal ──────────────────────────────────────────────────────────

class OffGridDesign:
    def __init__(
        self,
        ubicacion: str,
        consumo_mensual_kwh: float,
        consumo_anual_promedio_kwh: float | None = None,
        potencia_demandada_w: float = 0,
        factor_uso: float = 0.6,
        dias_autonomia: int = 1,
        tipo_bateria: str = "LiFePO4",
        tension_sistema: int | None = None,
        panel_id: str | None = None,
        lat: float | None = None,
        lon: float | None = None,
        modo_coordenadas: str = "auto",  # "provincia" | "coordenadas" | "hibrido"
    ):
        self.ubicacion = ubicacion.strip()
        self.consumo_mensual_kwh = consumo_mensual_kwh
        self.consumo_anual_promedio_kwh = consumo_anual_promedio_kwh or consumo_mensual_kwh
        self.potencia_demandada_w = potencia_demandada_w
        self.factor_uso = factor_uso
        self.dias_autonomia = dias_autonomia
        self.tipo_bateria = tipo_bateria
        self.modo_coordenadas = modo_coordenadas
        self.pasos: list[str] = []  # registro paso a paso

        # ── Paso 1: Recurso solar ─────────────────────────────────────────────
        if modo_coordenadas == "coordenadas" and lat is not None and lon is not None:
            hsp_anual, hsp_peor, hsp_mes, prov_cercana = hsp_por_coordenadas(lat, lon)
            self.latitud = round(lat, 4)
            self.longitud = round(lon, 4)
            self.provincia_detectada = prov_cercana
            self.hsp_anual = hsp_anual
            self.hsp_peor_mes = hsp_peor
            self.hsp_por_mes = hsp_mes
            peor_mes_idx = hsp_mes.index(hsp_peor) + 1
            self.pasos.append(
                f"[1] Recurso solar · Coordenadas GPS ({lat:.4f}, {lon:.4f})\n"
                f"     Provincia más cercana detectada: {prov_cercana}\n"
                f"     HSP anual (interpolado): {hsp_anual} h\n"
                f"     HSP peor mes (mes {peor_mes_idx}): {hsp_peor} h"
            )
        else:
            prov = buscar_provincia(self.ubicacion)
            if not prov:
                raise ValueError(f"Ubicación no encontrada: '{self.ubicacion}'. Usá coordenadas GPS o nombre de provincia válido.")
            self.latitud = prov["lat"]
            self.longitud = None
            self.provincia_detectada = self.ubicacion
            self.hsp_anual = round(prov["hsp_mensual"][1], 2)
            self.hsp_peor_mes = round(min(prov["hsp_mensual"]), 2)
            self.hsp_por_mes = prov["hsp_mensual"]
            peor_mes_idx = prov["hsp_mensual"].index(self.hsp_peor_mes) + 1
            self.pasos.append(
                f"[1] Recurso solar · {self.ubicacion} (lat {self.latitud}°S)\n"
                f"     HSP anual: {self.hsp_anual} h\n"
                f"     HSP peor mes (mes {peor_mes_idx}): {self.hsp_peor_mes} h"
            )

        # ── Paso 2: Demanda diaria ────────────────────────────────────────────
        self.consumo_diario_kwh = self.consumo_anual_promedio_kwh / 30.44
        self.consumo_diario_wh = self.consumo_diario_kwh * 1000
        self.pasos.append(
            f"[2] Demanda diaria\n"
            f"     {self.consumo_anual_promedio_kwh} kWh/mes ÷ 30.44 días = {self.consumo_diario_kwh:.2f} kWh/día = {self.consumo_diario_wh:.0f} Wh/día"
        )

        # ── Paso 3: Factor de corrección ──────────────────────────────────────
        self.factor_perdidas = 1.30
        self.consumo_real_wh = round(self.consumo_diario_wh * self.factor_perdidas, 1)
        self.pasos.append(
            f"[3] Factor de corrección global = {self.factor_perdidas} (pérdidas: controlador 3% + inversor 8% + cables 3% + baterías 8% + suciedad/temp 3% + margen)\n"
            f"     {self.consumo_diario_wh:.0f} Wh × {self.factor_perdidas} = {self.consumo_real_wh} Wh/día ← DEMANDA REAL DEL SISTEMA"
        )

        # ── Paso 4: Demanda simultánea ────────────────────────────────────────
        if self.potencia_demandada_w > 0:
            self.demanda_sim_w = round(self.potencia_demandada_w * self.factor_uso, 0)
            self.pasos.append(
                f"[4] Demanda simultánea\n"
                f"     {self.potencia_demandada_w} W × {self.factor_uso} (factor uso) = {self.demanda_sim_w:.0f} W"
            )
        else:
            self.demanda_sim_w = round(self.consumo_real_wh / 24 * self.factor_uso, 0)
            self.pasos.append(
                f"[4] Demanda simultánea (sin dato de pico, estimada)\n"
                f"     {self.consumo_real_wh:.0f} Wh / 24 h × {self.factor_uso} = {self.demanda_sim_w:.0f} W"
            )

        # ── Paso 5: Tensión sugerida ───────────────────────────────────────────
        if tension_sistema is None:
            if self.demanda_sim_w <= 500:
                tension_sistema = 12
            elif self.demanda_sim_w <= 1500:
                tension_sistema = 24
            else:
                tension_sistema = 48
        self.tension_sistema = tension_sistema
        self.pasos.append(
            f"[5] Tensión del sistema\n"
            f"     Demanda {self.demanda_sim_w:.0f}W → {'12V' if tension_sistema==12 else '24V' if tension_sistema==24 else '48V'}"
        )

        # ── Paso 6: Panel ──────────────────────────────────────────────────────
        self.panel, justificacion_panel = elegir_panel(panel_id, self.consumo_real_wh, self.hsp_anual)
        self.pasos.append(f"[6] Selección de panel\n     {justificacion_panel}")

        # ── Paso 7: Paneles necesarios ────────────────────────────────────────
        self.energia_panel_anual = round(self.panel["potencia_wp"] * self.hsp_anual * 0.87, 1)
        self.energia_panel_peor = round(self.panel["potencia_wp"] * self.hsp_peor_mes * 0.87, 1)
        self.n_paneles_anual = math.ceil(self.consumo_real_wh / self.energia_panel_anual)
        self.n_paneles_peor = math.ceil(self.consumo_real_wh / self.energia_panel_peor)
        self.n_paneles = max(self.n_paneles_anual, 2)
        self.pasos.append(
            f"[7] Cantidad de paneles\n"
            f"     Energía por panel (promedio): {self.panel['potencia_wp']} Wp × {self.hsp_anual} HSP × 0.87 = {self.energia_panel_anual:.0f} Wh/día\n"
            f"     Energía por panel (peor mes): {self.panel['potencia_wp']} Wp × {self.hsp_peor_mes} HSP × 0.87 = {self.energia_panel_peor:.0f} Wh/día\n"
            f"     Necesarios por promedio: {self.consumo_real_wh:.0f} / {self.energia_panel_anual:.0f} = {self.n_paneles_anual}\n"
            f"     Necesarios por peor mes: {self.consumo_real_wh:.0f} / {self.energia_panel_peor:.0f} = {self.n_paneles_peor}\n"
            f"     → Se adoptan {self.n_paneles} paneles"
        )

        # ── Paso 8: Strings ────────────────────────────────────────────────
        self._configurar_strings()
        self.pasos.append(
            f"[8] Configuración de strings ({self.n_paneles} paneles)\n"
            f"     Candidatas evaluadas que cubren {self.n_paneles} paneles → mejor acercamiento a {self.tension_sistema * 2.5:.0f}V (2.5× Vbanco)\n"
            f"     Elegida: {self.serie}S × {self.paralelo}P\n"
            f"       Vmp string: {self.serie} × {self.panel['vmp']} = {self.vmp_string} V\n"
            f"       Voc string (frío, +5%): {self.serie} × {self.panel['voc']} × 1.05 = {self.voc_string} V\n"
            f"       Corriente por string: {self.corriente_string_a} A\n"
            f"       Corriente total: {self.corriente_total_a} A"
        )

        # ── Paso 9: Baterías ─────────────────────────────────────────────────
        self._dimensionar_baterias()
        self.pasos.append(
            f"[9] Banco de baterías\n"
            f"     Criterio DoD: {int(self.bateria['dod_max']*100)}% (LiFePO4 eficiencia {int(self.bateria['eficiencia']*100)}%)\n"
            f"     Cap. requerida = ({self.consumo_real_wh:.0f} × {self.dias_autonomia}) / ({self.tension_sistema} × {self.bateria['dod_max']} × {self.bateria['eficiencia']})\n"
            f"                   = {self.capacidad_requerida_ah} Ah\n"
            f"     Baterías: {self.n_baterias} × {self.bateria['capacidad_ah']} Ah = {self.energia_banco_wh:.0f} Wh totales\n"
            f"     Energía útil: {self.energia_util_wh:.0f} Wh (DoD {int(self.bateria['dod_max']*100)}%)\n"
            f"     Autonomía real: {self.energia_util_wh:.0f} / {self.consumo_real_wh:.0f} = {self.autonomia_real_dias} días"
        )

        # ── Paso 10: Controlador ───────────────────────────────────────────────
        self._dimensionar_controlador()
        just_controlador = elegir_controlador(self.tension_sistema, self.corriente_total_a, self.voc_string)
        # guardar solo la razonada (no usamos la otra función porque ya fue seleccionado)
        self.pasos.append(
            f"[10] Controlador de carga\n"
            f"     Corriente del array: {self.corriente_total_a} A\n"
            f"     Voc del array: {self.voc_string} V\n"
            f"     → {self.controlador['marca']} {self.controlador['modelo']} ({self.controlador['tipo']}, {self.controlador['corriente_max_a']}A, Voc máx {self.controlador['voc_max_v']}V)"
        )

        # ── Paso 11: Inversor ─────────────────────────────────────────────────
        self._dimensionar_inversor()
        self.pasos.append(
            f"[11] Inversor\n"
            f"     Demanda simultánea: {self.demanda_sim_w:.0f} W\n"
            f"     → {self.inversor['marca']} {self.inversor['modelo']} ({self.inversor['potencia_nominal_w']}W, pico {self.inversor['potencia_pico_w']}W)"
        )

        # ── Paso 12: Protecciones ──────────────────────────────────────────────
        self._calcular_protecciones()
        self.pasos.append(
            f"[12] Protecciones\n"
            f"     Fusible string: Isc×1.25 = {self.panel['isc']}×1.25 = {self.fusible_string_a} A\n"
            f"     Fusible combiner: {self.paralelo} strings × {self.panel['isc']}×1.25 = {self.fusible_combiner_a} A\n"
            f"     Fusible carga: ({self.n_paneles}×{self.panel['potencia_wp']})/{self.tension_sistema}×1.25 = {self.fusible_carga_a} A\n"
            f"     Disyuntor AC: {self.inversor['potencia_nominal_w']}/230×1.25 = {self.disyuntor_ac_a} A bipolar"
        )

        # ── Paso 13: Presupuesto ──────────────────────────────────────────────
        self._calcular_presupuesto()
        self.pasos.append(
            f"[13] Presupuesto referencial (materiales)\n"
            f"     {self.n_paneles} paneles × ${self.panel['precio_ars']:,} = ${self.n_paneles * self.panel['precio_ars']:,}\n"
            f"     {self.n_baterias} baterías × ${self.bateria['precio_ars']:,} = ${self.n_baterias * self.bateria['precio_ars']:,}\n"
            f"     1 inversor {self.inversor['marca']} = ${self.inversor['precio_ars']:,}\n"
            f"     1 controlador {self.controlador['marca']} = ${self.controlador['precio_ars']:,}\n"
            f"     TOTAL = ${self.presupuesto_materiales_ars:,} ARS"
        )

    # ── Internals ─────────────────────────────────────────────────────────────

    def _configurar_strings(self):
        n = self.n_paneles
        vmp = self.panel["vmp"]
        voc = self.panel["voc"]
        tension_banco = self.tension_sistema
        mejor_config = (1, n)
        mejor_diff = 9999
        for serie in range(1, n + 1):
            paralelo = math.ceil(n / serie)
            if serie * paralelo < n:
                continue
            vmp_str = serie * vmp
            diff = abs(vmp_str - tension_banco * 2.5)
            if diff < mejor_diff:
                mejor_diff = diff
                mejor_config = (serie, paralelo)
        serie, paralelo = mejor_config
        self.serie = serie
        self.paralelo = paralelo
        self.vmp_string = round(serie * vmp, 1)
        self.voc_string = round(serie * voc * 1.05, 1)
        self.corriente_string_a = round(self.panel["imp"], 2)
        self.corriente_total_a = round(paralelo * self.panel["imp"], 2)

    def _dimensionar_baterias(self):
        tension = self.tension_sistema
        self.bateria, _ = elegir_bateria(tension, self.tipo_bateria)
        bat = self.bateria
        dod = bat["dod_max"] if bat["tipo"] == "LiFePO4" else 0.50
        eficiencia = bat["eficiencia"]
        capacidad_ah = round(
            (self.consumo_real_wh * self.dias_autonomia) / (tension * dod * eficiencia), 0
        )
        self.capacidad_requerida_ah = int(capacidad_ah)
        self.n_baterias = math.ceil(capacidad_ah / bat["capacidad_ah"])
        self.energia_banco_wh = self.n_baterias * bat["energia_wh"]
        self.energia_util_wh = round(self.energia_banco_wh * dod, 0)
        self.autonomia_real_dias = round(self.energia_util_wh / self.consumo_real_wh, 2)

    def _dimensionar_controlador(self):
        self.controlador, _ = elegir_controlador(
            self.tension_sistema,
            self.corriente_total_a,
            self.voc_string,
        )

    def _dimensionar_inversor(self):
        self.inversor, _ = elegir_inversor(self.tension_sistema, self.demanda_sim_w)

    def _calcular_protecciones(self):
        isc = self.panel["isc"]
        n_strings = self.paralelo
        self.fusible_string_a = math.ceil(isc * 1.25)
        self.fusible_combiner_a = math.ceil(n_strings * isc * 1.25)
        i_carga = (self.n_paneles * self.panel["potencia_wp"]) / self.tension_sistema
        self.fusible_carga_a = math.ceil(i_carga * 1.25)
        i_inv_ac = self.inversor["potencia_nominal_w"] / 230
        self.disyuntor_ac_a = math.ceil(i_inv_ac * 1.25 / 2) * 2

    def _calcular_presupuesto(self):
        total = self.n_paneles * self.panel["precio_ars"]
        total += self.n_baterias * self.bateria["precio_ars"]
        total += self.inversor["precio_ars"]
        total += self.controlador["precio_ars"]
        self.presupuesto_materiales_ars = total

    # ── Reporte ───────────────────────────────────────────────────────────────

    def to_dict(self) -> dict:
        return {
            "ubicacion": self.ubicacion,
            "latitud": self.latitud,
            "consumo_mensual_kwh": self.consumo_mensual_kwh,
            "consumo_anual_promedio_kwh": self.consumo_anual_promedio_kwh,
            "consumo_diario_wh": round(self.consumo_diario_wh, 1),
            "consumo_real_wh": self.consumo_real_wh,
            "potencia_demandada_w": self.potencia_demandada_w,
            "factor_uso": self.factor_uso,
            "demanda_sim_w": round(self.demanda_sim_w, 0),
            "dias_autonomia": self.dias_autonomia,
            "tension_sistema": self.tension_sistema,
            "tipo_bateria": self.tipo_bateria,
            "hsp_anual": self.hsp_anual,
            "hsp_peor_mes": self.hsp_peor_mes,
            "hsp_por_mes": self.hsp_por_mes,
            "panel": {
                "id": self.panel["id"],
                "marca": self.panel["marca"],
                "modelo": self.panel["modelo"],
                "potencia_wp": self.panel["potencia_wp"],
                "vmp": self.panel["vmp"],
                "voc": self.panel["voc"],
                "isc": self.panel["isc"],
            },
            "n_paneles": self.n_paneles,
            "energia_panel_anual_wh": self.energia_panel_anual,
            "energia_panel_peor_wh": self.energia_panel_peor,
            "config_strings": {
                "serie": self.serie,
                "paralelo": self.paralelo,
                "vmp_string_v": self.vmp_string,
                "voc_string_v": self.voc_string,
                "corriente_string_a": self.corriente_string_a,
                "corriente_total_a": self.corriente_total_a,
            },
            "bateria": {
                "id": self.bateria["id"],
                "tipo": self.bateria["tipo"],
                "modelo": self.bateria["modelo"],
                "tension_nominal_v": self.bateria["tension_nominal_v"],
                "capacidad_ah": self.bateria["capacidad_ah"],
                "dod_max": self.bateria["dod_max"],
                "eficiencia": self.bateria["eficiencia"],
            },
            "n_baterias": self.n_baterias,
            "capacidad_requerida_ah": self.capacidad_requerida_ah,
            "energia_banco_wh": self.energia_banco_wh,
            "energia_util_wh": self.energia_util_wh,
            "autonomia_real_dias": self.autonomia_real_dias,
            "controlador": {
                "id": self.controlador["id"],
                "marca": self.controlador["marca"],
                "modelo": self.controlador["modelo"],
                "tipo": self.controlador["tipo"],
                "corriente_max_a": self.controlador["corriente_max_a"],
                "voc_max_v": self.controlador["voc_max_v"],
            },
            "inversor": {
                "id": self.inversor["id"],
                "marca": self.inversor["marca"],
                "modelo": self.inversor["modelo"],
                "potencia_nominal_w": self.inversor["potencia_nominal_w"],
                "potencia_pico_w": self.inversor["potencia_pico_w"],
                "tension_banco_v": self.inversor["tension_banco_v"],
            },
            "protecciones": {
                "fusible_string_a": self.fusible_string_a,
                "fusible_combiner_a": self.fusible_combiner_a,
                "fusible_carga_a": self.fusible_carga_a,
                "disyuntor_ac_a": self.disyuntor_ac_a,
            },
            "presupuesto_materiales_ars": self.presupuesto_materiales_ars,
            "pasos": self.pasos,
        }
