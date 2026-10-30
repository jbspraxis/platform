"""Square post cards (1080x1080) for the first JBS Praxis posts, one design used on X, LinkedIn and Instagram.
Mist field, horizontal logo, Newsreader line, plain support line, the Praxis rule, jbspraxis.com.
python brand/build/build_posts.py  ->  brand/kit/social/posts/
"""
from pathlib import Path

import build_kit as kit

OUT = kit.KIT / 'social' / 'posts'
S = 1080

POSTS = [
    ('post-1-ask-your-company-anything.png', 'Ask your company anything.',
     'We design how your organisation runs, then build the system that answers for it.'),
    ('post-2-a-dozen-tools.png', 'A dozen tools. Not one answer.',
     'Email, chat, accounts, sales: each tool answers for itself. We build the one that answers for the company.'),
    ('post-3-cost-of-poor-quality.png', 'What is poor quality costing you?',
     'Rework, delays, errors and double entry have a price. Estimate yours in a few minutes.'),
]


def card(line, support, pid):
    logo_h = 46
    logo = kit.svg_doc(f'0 0 {kit.HZ_W:.2f} {kit.H}', kit.horizontal_body('colour', pid), 'JBS Praxis',
                       round(logo_h * kit.HZ_W / kit.H), logo_h)
    body = (f'<div style="position:relative;width:{S}px;height:{S}px;background:#F2F5F7;overflow:hidden;'
            f'box-sizing:border-box;padding:96px;display:flex;flex-direction:column;justify-content:space-between">'
            f'<div>{logo}</div>'
            f'<div><div style="font:400 92px/1.06 Newsreader,Georgia,serif;letter-spacing:-0.015em;color:#0C142B;max-width:860px">{line}</div>'
            f'<div style="display:flex;width:360px;height:5px;margin:44px 0 40px"><i style="flex:1;background:#0C142B"></i><i style="flex:1;background:#0C8B9B"></i></div>'
            f'<div style="font:500 34px/1.4 \'Plus Jakarta Sans\',Arial,sans-serif;color:#4A5468;max-width:820px">{support}</div></div>'
            f'<div style="font:600 28px/1 \'Plus Jakarta Sans\',Arial,sans-serif;color:#0C8B9B">jbspraxis.com</div>'
            '</div>')
    return kit.page(body, '#F2F5F7', fonts=True)


def main():
    for i, (name, line, support) in enumerate(POSTS, 1):
        kit.shoot(Path(name).stem, card(line, support, f'pc{i}'), S, S, OUT / name)
        print('ok', name)


if __name__ == '__main__':
    main()
