// The quiet flow field behind the homepage hero (docs/BRAND.md section 11, "Quiet flow", v1.4): fine horizontal
// lines, the Praxis rule multiplied, Ink on the left to Teal on the right. On arrival they are loose and wavy, then
// they converge into calm, ordered rows (order, made visible) and drift on a slow cycle. Lines near the pointer lift
// a little. Drawn by one fragment shader on a single canvas, at most 30 frames a second, paused off screen and in a
// hidden tab. Without WebGL the SVG drawing in .hero-field stays.
const VERTEX = `
attribute vec2 p;
void main() { gl_Position = vec4(p, 0.0, 1.0); }`;

const FRAGMENT = `
precision mediump float;
uniform vec2 res;
uniform float time;
uniform float loose;
uniform vec2 pointer;
const vec3 INK = vec3(0.047, 0.078, 0.169);
const vec3 TEAL = vec3(0.047, 0.545, 0.608);
const int LINES = 22;
void main() {
    vec2 uv = gl_FragCoord.xy / res;
    float alpha = 0.0;
    for (int i = 0; i < LINES; i++) {
        float f = float(i);
        float row = (f + 0.5) / float(LINES);
        float amp = mix(0.004, 0.045, loose) * (0.65 + 0.35 * sin(f * 1.7));
        float y = row + amp * sin(uv.x * 6.2832 * (0.55 + 0.04 * f) + time * 0.22 + f * 0.9);
        vec2 d = vec2((uv.x - pointer.x) * 5.0, (row - pointer.y) * 7.0);
        y += 0.018 * exp(-dot(d, d));
        float px = abs(uv.y - y) * res.y;
        float strength = mod(f, 5.0) < 0.5 ? 0.30 : 0.15;
        alpha = max(alpha, (1.0 - smoothstep(0.0, 1.15, px)) * strength);
    }
    alpha *= smoothstep(0.18, 0.62, uv.x);
    vec3 colour = mix(INK, TEAL, smoothstep(0.35, 0.95, uv.x));
    gl_FragColor = vec4(colour * alpha, alpha);
}`;

function compile(gl, type, source) {
    const shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    return gl.getShaderParameter(shader, gl.COMPILE_STATUS) ? shader : null;
}

export function startField(host) {
    if (!host) return;
    const canvas = document.createElement('canvas');
    const gl = canvas.getContext('webgl', { alpha: true, antialias: false, premultipliedAlpha: true, powerPreference: 'low-power' });
    if (!gl) return;

    const vertex = compile(gl, gl.VERTEX_SHADER, VERTEX);
    const fragment = compile(gl, gl.FRAGMENT_SHADER, FRAGMENT);
    if (!vertex || !fragment) return;
    const program = gl.createProgram();
    gl.attachShader(program, vertex);
    gl.attachShader(program, fragment);
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) return;
    gl.useProgram(program);

    const buffer = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
    const position = gl.getAttribLocation(program, 'p');
    gl.enableVertexAttribArray(position);
    gl.vertexAttribPointer(position, 2, gl.FLOAT, false, 0, 0);
    const uniform = (name) => gl.getUniformLocation(program, name);
    const u = { res: uniform('res'), time: uniform('time'), loose: uniform('loose'), pointer: uniform('pointer') };

    host.prepend(canvas);
    host.classList.add('has-gl');

    const resize = () => {
        const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
        canvas.width = Math.max(1, Math.round(host.clientWidth * ratio));
        canvas.height = Math.max(1, Math.round(host.clientHeight * ratio));
        gl.viewport(0, 0, canvas.width, canvas.height);
        gl.uniform2f(u.res, canvas.width, canvas.height);
    };
    resize();
    new ResizeObserver(resize).observe(host);

    // The pointer, eased so the lines follow it softly; parked far away when it leaves
    const aim = { x: 2, y: 2 };
    const at = { x: 2, y: 2 };
    host.parentElement.addEventListener('pointermove', (event) => {
        const box = host.getBoundingClientRect();
        aim.x = (event.clientX - box.left) / box.width;
        aim.y = 1 - (event.clientY - box.top) / box.height;
    });
    host.parentElement.addEventListener('pointerleave', () => { aim.x = 2; aim.y = 2; });

    let visible = true;
    new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; }).observe(host);

    const born = performance.now();
    let last = 0;
    const frame = (now) => {
        requestAnimationFrame(frame);
        if (!visible || document.hidden || now - last < 33) return;
        last = now;
        const t = (now - born) / 1000;
        const loose = Math.max(0, 1 - t / 1.6) ** 2; // converge over 1.6 s, then calm
        at.x += (aim.x - at.x) * 0.08;
        at.y += (aim.y - at.y) * 0.08;
        gl.uniform1f(u.time, t);
        gl.uniform1f(u.loose, loose);
        gl.uniform2f(u.pointer, at.x, at.y);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    };
    requestAnimationFrame(frame);
}
