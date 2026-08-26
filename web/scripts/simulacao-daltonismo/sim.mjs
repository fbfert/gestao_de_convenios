// Simulacao de daltonismo (matrizes de Machado et al., 2009, severidade 1.0)
// e distancia perceptual CIE76 em Lab entre os pares semanticos.
const MATRIZES = {
  deuteranopia: [[0.367322,0.860646,-0.227968],[0.280085,0.672501,0.047413],[-0.011820,0.042940,0.968881]],
  protanopia:   [[0.152286,1.052583,-0.204868],[0.114503,0.786281,0.099216],[-0.003882,-0.048116,1.051998]],
  tritanopia:   [[1.255528,-0.076749,-0.178779],[-0.078411,0.930809,0.147602],[0.004733,0.691367,0.303900]],
}
const hex = (h) => { h=h.replace('#',''); if(h.length===3) h=[...h].map(c=>c+c).join('')
  return [0,2,4].map(i=>parseInt(h.slice(i,i+2),16)) }
const lin = (c)=>{c/=255; return c<=0.04045?c/12.92:((c+0.055)/1.055)**2.4}
const delin = (c)=>{const v=c<=0.0031308?c*12.92:1.055*c**(1/2.4)-0.055; return Math.max(0,Math.min(255,Math.round(v*255)))}
const simular = (rgb, tipo) => {
  const m = MATRIZES[tipo], [r,g,b] = rgb.map(lin)
  return [0,1,2].map(i => delin(m[i][0]*r + m[i][1]*g + m[i][2]*b))
}
const lab = ([r,g,b]) => {
  const [R,G,B]=[r,g,b].map(lin)
  let X=(R*0.4124+G*0.3576+B*0.1805)/0.95047, Y=R*0.2126+G*0.7152+B*0.0722, Z=(R*0.0193+G*0.1192+B*0.9505)/1.08883
  const f=(t)=>t>0.008856?Math.cbrt(t):(7.787*t+16/116)
  ;[X,Y,Z]=[f(X),f(Y),f(Z)]
  return [116*Y-16, 500*(X-Y), 200*(Y-Z)]
}
const dE = (a,b)=>{const [l1,a1,b1]=lab(a),[l2,a2,b2]=lab(b); return Math.hypot(l1-l2,a1-a2,b1-b2)}

export function avaliar(nome, cores, pares) {
  console.log(`\n### ${nome}`)
  for (const tipo of ['deuteranopia','protanopia','tritanopia']) {
    const ruins = []
    for (const [a,b] of pares) {
      const d = dE(simular(hex(cores[a]),tipo), simular(hex(cores[b]),tipo))
      if (d < 25) ruins.push(`${a}/${b}=${d.toFixed(0)}`)
    }
    const marca = ruins.length ? '✗' : '✓'
    console.log(`  ${marca} ${tipo.padEnd(13)} ${ruins.length ? 'colapsam: ' + ruins.join('  ') : 'todos os pares distinguiveis'}`)
  }
}
