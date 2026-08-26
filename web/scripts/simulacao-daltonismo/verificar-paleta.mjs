import { avaliar } from './sim.mjs'
const hex=(h)=>{h=h.replace('#','');if(h.length===3)h=[...h].map(c=>c+c).join('');return [0,2,4].map(i=>parseInt(h.slice(i,i+2),16))}
const lum=([r,g,b])=>{const f=(c)=>{c/=255;return c<=0.03928?c/12.92:((c+0.055)/1.055)**2.4};return 0.2126*f(r)+0.7152*f(g)+0.0722*f(b)}
const cr=(a,b)=>{const [x,y]=[lum(hex(a)),lum(hex(b))];const [hi,lo]=x>y?[x,y]:[y,x];return (hi+0.05)/(lo+0.05)}

// O canal CROMATICO e a BORDA — e ela que carrega a identidade da cor.
const BORDA = { acento:'#0E2A3D', sucesso:'#0069B0', perigo:'#7F2B00', alerta:'#A87900', info:'#605A50' }
avaliar('bordas (canal cromatico)', BORDA, [
  ['sucesso','perigo'],['sucesso','acento'],['sucesso','alerta'],['perigo','alerta'],
  ['perigo','acento'],['alerta','info'],['info','acento'],['info','sucesso'],['perigo','info'],['alerta','acento']])

// O canal de LEGIBILIDADE e o texto sobre o preenchimento suave.
const TEXTO = { sucesso:'#00456F', perigo:'#7F2B00', alerta:'#6E4E00', info:'#4A4540' }
const FILL  = { sucesso:'#E7F0F8', perigo:'#FBEDE5', alerta:'#FDF6E4', info:'#EFEDEA' }
console.log('\n### contraste')
let ok = true
for (const k of Object.keys(TEXTO)) {
  const r = cr(TEXTO[k], FILL[k])
  if (r < 4.5) ok = false
  console.log(`  ${r>=4.5?'✓':'✗'} texto ${k.padEnd(8)} sobre o preenchimento  ${r.toFixed(2)}:1  (piso 4.5)`)
}
for (const k of Object.keys(BORDA)) {
  const r = cr(BORDA[k], '#FFFFFF')
  if (r < 3) ok = false
  console.log(`  ${r>=3?'✓':'✗'} borda ${k.padEnd(8)} sobre a superficie     ${r.toFixed(2)}:1  (piso 3)`)
}
console.log(`  ${cr('#FFFFFF', BORDA.acento)>=4.5?'✓':'✗'} branco sobre o acento solido        ${cr('#FFFFFF',BORDA.acento).toFixed(2)}:1  (piso 4.5)`)
console.log(`\n  ${ok?'APROVADO':'REPROVADO'}`)
