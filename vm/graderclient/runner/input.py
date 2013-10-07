import sys, json

exec open(sys.argv[1]).read()

print json.dumps([unicode(x) for x in run()])