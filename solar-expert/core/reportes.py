from datetime import date


def generar_html(d: dict) -> str:
    hoy = date.today().strftime("%d/%m/%Y")
    strings = d["config_strings"]
    bat = d["bateria"]
    inv = d["inversor"]
    ctrl = d["controlador"]
    prot = d["protecciones"]

    hsp_mes_fila = "".join(
        f"<tr><td>{i+1}</td><td>{v}</td></tr>" for i, v in enumerate(d["hsp_por_mes"])
    )

    return f"""<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Informe Off-Grid - {d['ubicacion']}</title>
<style>
  * {{ margin:0; padding:0; box-sizing:border-box; }}
  body {{ font-family: 'Segoe UI', Arial, sans-serif; color:#111; background:#f4f5f7; padding:30px; }}
  .wrap {{ max-width:900px; margin:auto; background:#fff; padding:36px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,.06); }}
  h1 {{ font-size:22px; margin-bottom:4px; }}
  h2 {{ font-size:16px; color:#222; margin:24px 0 8px; border-bottom:2px solid #0a66c2; padding-bottom:4px; }}
  .meta {{ color:#555; font-size:13px; margin-bottom:18px; }}
  table {{ width:100%; border-collapse:collapse; margin-top:8px; font-size:14px; }}
  th, td {{ border:1px solid #ddd; padding:7px 10px; text-align:left; }}
  th {{ background:#f0f4fa; font-weight:600; }}
  .highlight {{ background:#fffbe6; }}
  .ok {{ color:#0a7e36; font-weight:600; }}
  .warn {{ color:#b35900; font-weight:600; }}
  pre {{ background:#f5f5f5; padding:14px; border-radius:6px; overflow:auto; font-size:13px; }}
  @media print {{ body {{ background:#fff; }} .wrap {{ box-shadow:none; }} }}
</style>
</head>
<body>
<div class="wrap">
  <h1>🌞 Informe de Sistema Solar Off-Grid</h1>
  <p class="meta">Generado el {hoy} · Ubicación: <strong>{d['ubicacion']}</strong> (lat {d['latitud']}°S)</p>

  <h2>📥 Datos de entrada</h2>
  <table>
    <tr><th>Parámetro</th><th>Valor</th></tr>
    <tr><td>Consumo mensual</td><td>{d['consumo_mensual_kwh']} kWh/mes</td></tr>
    <tr><td>Consumo anual promedio</td><td>{d['consumo_anual_promedio_kwh']} kWh/mes</td></tr>
    <tr><td>Potencia demandada (pico)</td><td>{d['potencia_demandada_w']} W</td></tr>
    <tr><td>Factor de uso</td><td>{d['factor_uso']}</td></tr>
    <tr><td>Días de autonomía</td><td>{d['dias_autonomia']}</td></tr>
    <tr><td>Tensión del sistema</td><td>{d['tension_sistema']} V</td></tr>
    <tr><td>Tecnología de baterías</td><td>{d['tipo_bateria']}</td></tr>
  </table>

  <h2>☀️ Recurso solar</h2>
  <table>
    <tr><th>Mes</th><th>HSP (kWh/m²/día)</th></tr>
    {hsp_mes_fila}
    <tr class="highlight"><td><strong>Promedio anual</strong></td><td><strong>{d['hsp_anual']}</strong></td></tr>
    <tr class="highlight"><td>Peor mes</td><td>{d['hsp_peor_mes']}</td></tr>
  </table>

  <h2>📊 Demanda</h2>
  <table>
    <tr><th>Concepto</th><th>Valor</th></tr>
    <tr><td>Consumo diario (sin corrección)</td><td>{d['consumo_diario_wh']:.0f} Wh/día</td></tr>
    <tr><td>Factor de corrección</td><td>1.30 (pérdidas)</td></tr>
    <tr><td>Demanda real del sistema</td><td class="ok">{d['consumo_real_wh']:.0f} Wh/día</td></tr>
    <tr><td>Demanda simultánea de diseño</td><td>{d['demanda_sim_w']:.0f} W</td></tr>
  </table>

  <h2>🔋 Banco de baterías</h2>
  <table>
    <tr><th>Concepto</th><th>Valor</th></tr>
    <tr><td>Modelo</td><td>{bat['marca']} {bat['modelo']} ({bat['tipo']})</td></tr>
    <tr><td>Tensión nominal</td><td>{bat['tension_nominal_v']} V</td></tr>
    <tr><td>Capacidad por unidad</td><td>{bat['capacidad_ah']} Ah</td></tr>
    <tr><td>Capacidad requerida</td><td>{d['capacidad_requerida_ah']} Ah</td></tr>
    <tr><td>Cantidad de baterías</td><td class="ok">{d['n_baterias']} unidades</td></tr>
    <tr><td>Energía total del banco</td><td>{d['energia_banco_wh']:.0f} Wh</td></tr>
    <tr><td>Energía útil (DoD {int(bat['dod_max']*100)}%)</td><td>{d['energia_util_wh']:.0f} Wh</td></tr>
    <tr><td>Autonomía real</td><td class="{'warn' if d['autonomia_real_dias'] < d['dias_autonomia'] else 'ok'}">{d['autonomia_real_dias']} días</td></tr>
  </table>

  <h2>🧩 Paneles solares</h2>
  <table>
    <tr><th>Concepto</th><th>Valor</th></tr>
    <tr><td>Modelo</td><td>{d['panel']['marca']} {d['panel']['modelo']}</td></tr>
    <tr><td>Potencia unitaria</td><td>{d['panel']['potencia_wp']} Wp</td></tr>
    <tr><td>Cantidad total</td><td class="ok">{d['n_paneles']} paneles</td></tr>
    <tr><td>Potencia total instalada</td><td>{d['n_paneles'] * d['panel']['potencia_wp']} Wp</td></tr>
    <tr><td>Generación anual estimada</td><td>{d['energia_panel_anual_wh']:.0f} Wh/día (prom.)</td></tr>
    <tr><td>Generación peor mes</td><td>{d['energia_panel_peor_wh']:.0f} Wh/día</td></tr>
    <tr><td>Configuración</td><td>{strings['serie']}S × {strings['paralelo']}P → Vmp string: {strings['vmp_string_v']} V · I total: {strings['corriente_total_a']} A</td></tr>
  </table>

  <h2>⚡ Controlador de carga</h2>
  <table>
    <tr><th>Concepto</th><th>Valor</th></tr>
    <tr><td>Modelo</td><td>{ctrl['marca']} {ctrl['modelo']} ({ctrl['tipo']})</td></tr>
    <tr><td>Rango MPPT</td><td>{ctrl['rango_mppt_v'][0]}-{ctrl['rango_mppt_v'][1]} V</td></tr>
    <tr><td>Voc máxima del array</td><td>{ctrl['voc_max_v']} V</td></tr>
    <tr><td>Corriente de carga (array)</td><td>{strings['corriente_total_a']} A</td></tr>
    <tr><td>Corriente máx. controlador</td><td>{ctrl['corriente_max_a']} A</td></tr>
  </table>

  <h2>🔌 Inversor</h2>
  <table>
    <tr><th>Concepto</th><th>Valor</th></tr>
    <tr><td>Modelo</td><td>{inv['marca']} {inv['modelo']}</td></tr>
    <tr><td>Potencia nominal</td><td>{inv['potencia_nominal_w']} W</td></tr>
    <tr><td>Potencia pico</td><td>{inv['potencia_pico_w']} W</td></tr>
    <tr><td>Tensión banco</td><td>{inv['tension_banco_v']} V</td></tr>
    <tr><td>Demanda simultánea a cubrir</td><td>{d['demanda_sim_w']:.0f} W</td></tr>
  </table>

  <h2>🛡️ Protecciones y conductores</h2>
  <table>
    <tr><th>Elemento</th><th>Valor</th></tr>
    <tr><td>Fusible por string</td><td>{prot['fusible_string_a']} A</td></tr>
    <tr><td>Fusible combiner</td><td>{prot['fusible_combiner_a']} A</td></tr>
    <tr><td>Fusible carga (MPPT→bat)</td><td>{prot['fusible_carga_a']} A</td></tr>
    <tr><td>Disyuntor bipolar AC</td><td>{prot['disyuntor_ac_a']} A / 230V</td></tr>
    <tr><td>Diferencial AC</td><td>25 A / 300 mA</td></tr>
    <tr><td>SPD tipo II CC</td><td>Sí (sobretensiones)</td></tr>
    <tr><td>Cable CC strings</td><td>{math.ceil(strings['corriente_string_a']*1.25)} mm² PV1-F</td></tr>
    <tr><td>Cable CC batería-inversor</td><td>{math.ceil(prot['fusible_carga_a']*1.25*0.5)+4} mm²</td></tr>
    <tr><td>Cable AC</td><td>NYM-J 3×{max(2.5, math.ceil(prot['disyuntor_ac_a']*0.5)+1)} mm²</td></tr>
  </table>

  <h2>💰 Presupuesto estimativo (materiales)</h2>
  <table>
    <tr><th>Componente</th><th>Cant.</th><th>Precio unit.</th><th>Subtotal</th></tr>
    <tr><td>Panel {d['panel']['marca']} {d['panel']['modelo']}</td><td>{d['n_paneles']}</td><td>${d['panel'].get('precio_ars',0):,}</td><td>${d['n_paneles']*d['panel'].get('precio_ars',0):,}</td></tr>
    <tr><td>Batería {bat['marca']} {bat['modelo']}</td><td>{d['n_baterias']}</td><td>${bat.get('precio_ars',0):,}</td><td>${d['n_baterias']*bat.get('precio_ars',0):,}</td></tr>
    <tr><td>Inversor {inv['marca']} {inv['modelo']}</td><td>1</td><td>${inv.get('precio_ars',0):,}</td><td>${inv.get('precio_ars',0):,}</td></tr>
    <tr><td>Controlador {ctrl['marca']} {ctrl['modelo']}</td><td>1</td><td>${ctrl.get('precio_ars',0):,}</td><td>${ctrl.get('precio_ars',0):,}</td></tr>
    <tr class="highlight"><td><strong>TOTAL MATERIALES</strong></td><td></td><td></td><td class="ok"><strong>${d['presupuesto_materiales_ars']:,}</strong></td></tr>
  </table>
  <p style="margin-top:8px;font-size:12px;color:#777">* Precios referenciales ARS, sujetos a variación. No incluye mano de obra ni estructura.</p>

  <h2>📋 Resumen final</h2>
  <table>
    <tr><th>Componente</th><th>Cantidad</th><th>Especificación clave</th></tr>
    <tr><td>Panel solar</td><td>{d['n_paneles']}</td><td>{d['panel']['marca']} {d['panel']['modelo']} · {d['panel']['potencia_wp']} Wp</td></tr>
    <tr><td>Controlador MPPT</td><td>1</td><td>{ctrl['marca']} {ctrl['modelo']} · {ctrl['corriente_max_a']}A · {ctrl['voc_max_v']}V</td></tr>
    <tr><td>Batería {bat['tipo']}</td><td>{d['n_baterias']}</td><td>{bat['marca']} {bat['modelo']} · {bat['tension_nominal_v']}V · {bat['capacidad_ah']}Ah</td></tr>
    <tr><td>Inversor</td><td>1</td><td>{inv['marca']} {inv['modelo']} · {inv['potencia_nominal_w']}W · {inv['tension_banco_v']}V</td></tr>
    <tr><td>Estructura</td><td>1 kit</td><td>Perfiles aluminio para {d['n_paneles']} paneles</td></tr>
    <tr><td>Cableado CC</td><td>+{d['n_paneles']*2} m</td><td>PV1-F según sección calculada</td></tr>
    <tr><td>Cableado AC</td><td>+20 m</td><td>NYM-J 3G</td></tr>
    <tr><td>Protecciones</td><td>1 set</td><td>Fusibles, disyuntores, diferencial, SPD II, jabalina tierra</td></tr>
  </table>

  <p style="margin-top:24px;font-size:12px;color:#aaa">Generado por Solar Expert · echo3d · {hoy}</p>
</div>
</body>
</html>"""
