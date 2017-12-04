# TalkBank

Scripts to create a Solr/[Mtas](https://meertensinstituut.github.io/mtas/) index by harvesting and processing [TalkBank](https://talkbank.org/) CMDI and Chat-XML resources.

A [docker](https://hub.docker.com/r/matthijsbrouwer/talkbank-childes-index/) image creating an index for part of the TalkBank [Childes](http://childes.talkbank.org/) collection is available. To build and run

```console
docker pull matthijsbrouwer/talkbank-childes-index
docker run -t -i -p 8080:80 --name talkbank-childes-index matthijsbrouwer/talkbank-childes-index
```

This will provide a website on port 8080 on the ip of your docker host containing a Solr instance on /solr/
with a core containing the constructed index, and a configured [Broker](https://meertensinstituut.github.io/broker/) instance on /broker/. The administrator account can be used with both `admin` / `admin` as login / password.

