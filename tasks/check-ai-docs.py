"""Read-only local-link and handoff-reference checks; no network, PHP, or DB calls."""
from pathlib import Path
import json
import re
import sys
from urllib.parse import unquote

sys.stdout.reconfigure(encoding='utf-8')
root = Path(__file__).resolve().parents[1]
ai = root / 'docs' / 'tài liệu AI'
files = [root / 'README.md', root / 'docs/local-ai.md', root / 'docs/mobile-api.md',
         *sorted(ai.rglob('*.md')), *sorted((root / 'tasks').rglob('*.md'))]
missing = []
for path in files:
    content = path.read_text(encoding='utf-8-sig')
    for match in re.finditer(r'\[[^\]\n]*\]\((<[^>\n]+>|[^)\n]+)\)', content):
        target = match.group(1).strip().strip('<>')
        if re.match(r'^[a-zA-Z][a-zA-Z0-9+.-]*:|^#', target):
            continue
        target = unquote(target.split('#')[0])
        if not target or '{' in target or '<' in target:
            continue
        if not (path.parent / target).exists():
            missing.append({'file': path.relative_to(root).as_posix(),
                            'line': content[:match.start()].count('\n') + 1, 'target': target})

references = [
    'app/Services/Support/SupportConversationService.php',
    'app/Services/Support/SupportDiagnosticFlow.php',
    'app/Services/HelpdeskAiService.php',
    'app/Support/OpenApiDocument.php',
    'database/migrations/2026_11_19_000001_create_support_session_tables.php',
    'database/migrations/2026_11_19_120000_add_support_conversation_workflow.php',
    'database/migrations/2026_11_20_090000_add_handoff_brief_to_support_messages.php',
    'database/migrations/2026_11_20_150000_add_soft_deletes_to_support_sessions.php',
    'tests/Feature/Support/SupportSessionTest.php',
    'tests/Feature/Support/SupportSessionDeletionTest.php',
    'tests/Feature/Support/SupportIntakeTest.php',
    'tests/Feature/Support/SupportDiagnosticFlowTest.php',
    'tests/Feature/Support/SupportHandoffBriefTest.php',
    'tests/Feature/SwaggerDocumentationTest.php',
    'tests/browser/support-diagnostic-smoke.mjs',
    'tests/browser/diagnostic-fixture.php',
    'tests/js/support-conversation.test.mjs',
]
missing_references = [path for path in references if not (root / path).is_file()]
requirements = (ai / '01_YEU_CAU.md').read_text(encoding='utf-8-sig')
trace = (ai / '06_KIEM_THU_VA_TRUY_VET.md').read_text(encoding='utf-8-sig')
fr_ids = set(re.findall(r'\| (AI-FR-\d{3}) \|', requirements))
missing_fr = sorted(code for code in fr_ids if code not in trace)
report = {
    'markdown_files': len(files), 'missing_local_targets': missing,
    'handoff_references_checked': len(references), 'missing_handoff_references': missing_references,
    'functional_requirements': len(fr_ids), 'functional_requirements_missing_from_trace': missing_fr,
    'limits': 'Checks local file targets, not Markdown anchors, external URLs, diagram rendering, runtime correctness, or completeness of TARGET acceptance criteria.',
}
print(json.dumps(report, ensure_ascii=False, indent=2))
sys.exit(1 if missing or missing_references or missing_fr else 0)
