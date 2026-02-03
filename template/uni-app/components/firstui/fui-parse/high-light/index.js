
/**
 * Modified by: firstui
 * organization: FirstUI(https://www.firstui.cn/)
 */
import hljs from './highlight.code.js'

import javascript from './languages/javascript.js'
import css from './languages/css.js'
import xml from './languages/xml.js'
import sql from './languages/sql.js'
import typescript from './languages/typescript.js'
import markdown from './languages/markdown.js'
import cpp from './languages/cpp.js'
import c from './languages/c.js'

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('css', css);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('typescript', typescript);
hljs.registerLanguage('markdown', markdown);
hljs.registerLanguage('c++', cpp);
hljs.registerLanguage('c', c);

export default hljs;