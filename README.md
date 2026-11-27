# A collection of offline HTML games.

[![Generate Offline HTML List](https://github.com/ert11er/Offline-HTML-Games-Pack/actions/workflows/ls.yml/badge.svg)](https://github.com/ert11er/Offline-HTML-Games-Pack/actions/workflows/ls.yml)

<p>A repo filled with 551 offline games that can each run on a single file. This is a good option if you are having internet connectivity issues, or you're at some place with <i>restricted internet</i>.</p>

Most of these games are from the [Ultimate Game Stash](https://docs.google.com/document/u/4/d/1_FmH3BlSBQI7FGgAQL59-ZPe8eCxs35wel6JUyVaG8Q) but turned offline.

**Usage:** Download [index.html](index.html) or use the [Vercel version](https://offline-html-games-pack-iota.vercel.app/) (recommended)

If your school blocked opening html files, save this code as a bookmarklet, open [about:blank](about:blank) and run the bookmarklet. Or just paste it to devtools on [about:blank](about:blank) :
```javascript
javascript:(function(){document.open();document.write('<!doctype html><meta charset=UTF-8><style>body{margin:0;overflow:hidden}iframe{position:fixed;inset:0;width:100%;height:100%;border:0}#p{position:relative;width:420px;height:200px;margin:20vh auto;padding:20px;border:2px dashed #aaa;text-align:center}#t{width:100%;box-sizing:border-box}#f,#b{position:fixed;top:5px;left:5px;z-index:9}#b{left:32px}</style><iframe id=w></iframe><div id=p><div id=d>Drag HTML file here or click me</div><input type=file id=i hidden><textarea id=t placeholder="Or paste here"></textarea><br><button id=r>Run</button></div><button id=f hidden>F</button><button id=b hidden>B</button><script>W=w;x=h=>{document.body.append(W);W.srcdoc=h;p.hidden=1;f.hidden=b.hidden=0};l=a=>{o=new FileReader;o.onload=()=>x(o.result);o.readAsText(a)};d.onclick=()=>i.click();i.onchange=()=>l(i.files[0]);d.ondragover=e=>e.preventDefault();d.ondrop=e=>{e.preventDefault();l(e.dataTransfer.files[0])};r.onclick=()=>x(t.value);f.onclick=()=>W.requestFullscreen();b.onclick=()=>{W.remove();p.hidden=0;f.hidden=b.hidden=1}<\/script>');document.close();})();
```

### Official Servers:
- https://offline-html-games-pack-iota.vercel.app/

### Community Servers:
- None Right Now

If you want to host your own community server, host [node-server](https://github.com/ert11er/Offline-HTML-Games-Pack/tree/node-server) (recommended) or [static-server](https://github.com/ert11er/Offline-HTML-Games-Pack/tree/static-server).
