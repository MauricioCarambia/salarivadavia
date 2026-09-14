function cargarERP() {

    fetch('api/dashboard.php')
        .then(r => r.json())
        .then(res => {
            let k = res.kpis;

            document.getElementById("kpiCaja").innerText = k.caja;
            document.getElementById("kpiFondo").innerText = k.fondo;
            document.getElementById("kpiTotal").innerText = k.total;
            document.getElementById("kpiDif").innerText = k.diferencia_hoy;

            document.getElementById("ingresos").innerText = res.flujo.ingresos;
            document.getElementById("egresos").innerText = res.flujo.egresos;
        });

    fetch('api/libro_diario.php')
        .then(r => r.json())
        .then(res => {
            let html = "";

            res.data.forEach(d => {
                html += `
                    <tr>
                        <td>${d.fecha}</td>
                        <td>${d.saldo_caja}</td>
                        <td>${d.saldo_fondo}</td>
                        <td>${d.saldo_total}</td>
                    </tr>
                `;
            });

            document.getElementById("libro").innerHTML = html;
        });
}

cargarERP();