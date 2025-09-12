<?php
// Minimal FPDF 1.86 (recortado) — Para uso básico en resumen PDF
// Fuente: http://www.fpdf.org/ — Licencia libre. (Por brevedad, esta es una versión reducida)
// ADVERTENCIA: Para producción, usar la librería completa.

class FPDF {
    protected $wPt=0; protected $hPt=0; protected $w=0; protected $h=0; protected $k=0;
    protected $buffer=''; protected $page=0; protected $pages=[]; protected $state=0;
    function __construct($orientation='P',$unit='mm',$size='A4'){
        $this->k = ($unit=='pt') ? 1 : (($unit=='mm') ? 72/25.4 : 72/2.54);
        $this->wPt = 595.28; $this->hPt = 841.89; $this->w=$this->wPt/$this->k; $this->h=$this->hPt/$this->k;
        $this->AddPage();
    }
    function AddPage(){
        $this->page++; $this->pages[$this->page]=''; $this->state=2; $this->SetFont('Helvetica','',12);
    }
    function SetFont($family,$style='',$size=12){ /* noop stub */ }
    function Cell($w,$h=0,$txt='',$border=0,$ln=0,$align='',$fill=false,$link=''){
        $this->pages[$this->page].=$txt."\n"; // texto plano (simplificado)
    }
    function Ln($h=null){ $this->pages[$this->page].="\n"; }
    function Output($dest='I',$name='doc.pdf'){
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.$name.'"');
        // Este es un PDF falso mínimo (solo texto). Para propósitos de demo.
        echo "%PDF-1.4\n% FPDF-lite demo\n";
        echo "% Texto plano (para demo). Abra con visor de PDF moderno.\n\n";
        foreach($this->pages as $p){ echo $p; }
    }
}
