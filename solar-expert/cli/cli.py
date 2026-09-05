#!/usr/bin/env python3
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "core"))
from engine import OffGridDesign
from reportes import generar_html


def pedir(prompt, default=None, tipo=float):
    while True:
        raw = input(f"{prompt} [{default}]: ").strip()
        if not raw:
            if default is not None:
                return default
        try:
            return tipo(raw)
        except ValueError:
            print("  Ingresá un valor numérico válido.")


def main():
    print("=" * 60)
    print("   SISTEMA EXPERTO - DIMENSIONAMIENTO SOLAR OFF-GRID")
    print("   echo3d · Solar Expert")
    print("=" * 60)

    print("\n1) Modo de ubicación:")
    print("   a) Provincia / ciudad")
    print("   b) Coordenadas GPS")
    modo = input("\nElegí (a/b) [a]: ").strip().lower() or "a"

    lat = lon = None
    ubicacion = ""
    if modo == "b":
        lat = pedir("\nLatitud (grados decimales, negativa para sur)", default=-32.0)
        lon = pedir("Longitud (grados decimales, negativa para oeste)", default=-58.0)
        ubicacion = f"Coordenadas ({lat}, {lon})"
        modo_calc = "coordenadas"
    else:
        provincias = [
            "Buenos Aires", "Catamarca", "Chaco", "Chubut", "Córdoba", "Corrientes",
            "Entre Ríos", "Formosa", "Jujuy", "La Pampa", "La Rioja", "Mendoza",
            "Misiones", "Neuquén", "Río Negro", "Salta", "San Juan", "San Luis",
            "Santa Cruz", "Santa Fe", "Santiago del Estero", "Tierra del Fuego", "Tucumán",
        ]
        print("\nProvincias disponibles:")
        for i, p in enumerate(provincias, 1):
            print(f"  {i:2d}. {p}")
        idx = pedir("\nNúmero de provincia", default=8, tipo=int)
        if 1 <= idx <= len(provincias):
            ubicacion = provincias[idx - 1]
        else:
            ubicacion = input("Ingresá el nombre de la provincia: ").strip()
        modo_calc = "provincia"

    print(f"\n  → Ubicación: {ubicacion}")

    consumo_mensual = pedir("\nConsumo mensual (kWh/mes)", default=450.0)
    consumo_anual = pedir("Consumo anual promedio (kWh/mes)", default=consumo_mensual)
    potencia_pico = pedir("Potencia demandada pico (W)", default=0.0)
    factor_uso = pedir("Factor de simultaneidad (0-1)", default=0.6)
    autonomia = pedir("Días de autonomía deseados", default=1, tipo=int)
    tension_str = pedir("Tensión del sistema V (12/24/48, 0=auto)", default=0, tipo=int)
    tension_sistema = tension_str if tension_str > 0 else None

    tipo_bat = input("\nTecnología de baterías [LiFePO4/Plomo-ácido] (default LiFePO4): ").strip()
    if tipo_bat not in ("LiFePO4", "Plomo-ácido"):
        tipo_bat = "LiFePO4"

    print("\n⏳ Calculando...\n")
    try:
        diseño = OffGridDesign(
            ubicacion=ubicacion,
            consumo_mensual_kwh=consumo_mensual,
            consumo_anual_promedio_kwh=consumo_anual,
            potencia_demandada_w=potencia_pico,
            factor_uso=factor_uso,
            dias_autonomia=autonomia,
            tipo_bateria=tipo_bat,
            tension_sistema=tension_sistema,
            lat=lat,
            lon=lon,
            modo_coordenadas=modo_calc,
        )
    except ValueError as e:
        print(f"❌ {e}")
        sys.exit(1)

    datos = diseño.to_dict()

    print(f"  Paneles necesarios  :  {datos['n_paneles']}")
    print(f"  Config. strings     :  {datos['config_strings']['serie']}S × {datos['config_strings']['paralelo']}P")
    print(f"  Voc por string      :  {datos['config_strings']['voc_string_v']} V")
    print(f"  Baterías            :  {datos['n_baterias']}")
    print(f"  Energía banco       :  {datos['energia_banco_wh']:.0f} Wh")
    print(f"  Autonomía real      :  {datos['autonomia_real_dias']} días")
    print(f"  Controlador         :  {datos['controlador']['marca']} {datos['controlador']['modelo']}")
    print(f"  Inversor            :  {datos['inversor']['marca']} {datos['inversor']['modelo']}")
    print(f"  Presupuesto mat.    :  ${datos['presupuesto_materiales_ars']:,} ARS")

    if datos.get("pasos"):
        print("\n── Pasos del cálculo ──────────────────────────")
        for p in datos["pasos"]:
            print(p)
            print()

    html = generar_html(datos)
    out = Path(__file__).resolve().parent.parent / f"informe_{datos['ubicacion'].replace(' ', '_')}.html"
    out.write_text(html, encoding="utf-8")
    print(f"✅ Informe guardado en: {out}")


if __name__ == "__main__":
    main()
